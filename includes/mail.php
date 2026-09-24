<?php
/**
 * Envoi d'e-mails.
 *
 * Point unique : deux chemins coexistaient. `envoyerEmail()` servait les
 * rappels ; `sendResetEmail()`, dans security.php, appelait `mail()`
 * directement avec ses propres en-têtes, pour la réinitialisation de mot de
 * passe. Le message le plus critique de l'application — celui sans lequel on
 * ne récupère pas son compte — partait donc par le chemin le moins soigné.
 *
 * ── Pourquoi `mail()` seul ne suffit pas ───────────────────────────────────
 *
 * `mail()` remet le message au sendmail de la machine, qui l'expédie sous
 * l'identité du serveur. Sur un mutualisé, cela veut dire :
 *
 *   • l'adresse d'enveloppe est celle du compte d'hébergement, pas la vôtre.
 *     Le SPF du domaine annoncé dans `From:` ne couvre pas cette machine, le
 *     contrôle échoue, et Gmail classe en indésirables — quand il ne refuse
 *     pas ;
 *   • rien n'est signé en DKIM, donc rien ne prouve l'origine du message ;
 *   • les rebonds ne reviennent nulle part : une adresse morte reste
 *     sollicitée indéfiniment sans qu'on l'apprenne.
 *
 * Ce fichier corrige ce qui peut l'être dans le code, et rend possible ce qui
 * ne peut l'être que dehors :
 *
 *   1. Des en-têtes complets et corrects — sujet encodé selon la RFC 2047
 *      (« Réinitialisation » partait en octets bruts, ce qui casse
 *      l'affichage et compte comme signal négatif), Message-ID, Date,
 *      MIME-Version, corps en quoted-printable, Auto-Submitted.
 *   2. Une adresse d'expéditeur configurable, et l'adresse d'enveloppe posée
 *      explicitement (`-f`) pour que SPF et rebonds s'alignent sur elle.
 *   3. Un transport SMTP authentifié optionnel. C'est la vraie réponse sur un
 *      mutualisé : le message part par le serveur du domaine ou par un service
 *      transactionnel, qui signe en DKIM. Sans dépendance : le dialogue SMTP
 *      tient en une centaine de lignes, et ajouter Composer en production pour
 *      cela seul coûterait plus cher que de l'écrire.
 *   4. Les échecs sont journalisés. `mail()` renvoyait false en silence.
 *
 * Ce que le code ne peut pas faire à votre place : publier les
 * enregistrements DNS. Voir docs/EMAIL.md.
 */

require_once __DIR__ . '/log.php';
require_once __DIR__ . '/config.php';

/**
 * La configuration d'envoi.
 *
 * @return array{expediteur:string, nom:string, retour:string, transport:string,
 *               hote:string, port:int, chiffrement:string, utilisateur:string,
 *               motdepasse:string}
 */
function configurationMail(): array
{
    // Par défaut, l'expéditeur est construit sur l'hôte servi : c'est le seul
    // domaine dont on soit sûr qu'il désigne bien cette application. Un
    // « noreply@linkee.app » écrit en dur mentait dès que le site
    // tournait ailleurs, et c'est précisément ce que SPF sanctionne.
    $hoteWeb = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $domaine = preg_replace('/:\d+$/', '', $hoteWeb) ?: 'localhost';

    $expediteur = reglage('MAIL_FROM', 'mail_from', 'noreply@' . $domaine);

    return [
        'expediteur'  => $expediteur,
        'nom'         => reglage('MAIL_FROM_NOM', 'mail_from_nom', 'Linkee'),
        // L'adresse d'enveloppe reçoit les rebonds. La même que l'expéditeur
        // par défaut : c'est ce qui aligne SPF sur le domaine annoncé.
        'retour'      => reglage('MAIL_RETURN_PATH', 'mail_return_path', $expediteur),
        // 'mail' = la fonction PHP ; 'smtp' = dialogue direct avec un serveur.
        'transport'   => strtolower(reglage('MAIL_TRANSPORT', 'mail_transport', 'mail')),
        'hote'        => reglage('MAIL_SMTP_HOTE', 'mail_smtp_hote', ''),
        'port'        => (int) reglage('MAIL_SMTP_PORT', 'mail_smtp_port', '587'),
        // 'tls' = STARTTLS sur 587, 'ssl' = TLS direct sur 465, '' = en clair.
        'chiffrement' => strtolower(reglage('MAIL_SMTP_CHIFFREMENT', 'mail_smtp_chiffrement', 'tls')),
        'utilisateur' => reglage('MAIL_SMTP_UTILISATEUR', 'mail_smtp_utilisateur', ''),
        'motdepasse'  => reglage('MAIL_SMTP_MOTDEPASSE', 'mail_smtp_motdepasse', ''),
    ];
}

/** Vrai lorsque l'application tourne sur un poste de développement. */
function estEnLocal(): bool
{
    if (PHP_SAPI === 'cli') {
        return !estEnProduction();
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');
}

/**
 * Nettoie une valeur destinée à un en-tête.
 *
 * Un saut de ligne dans un sujet ou une adresse permet d'injecter des
 * en-têtes arbitraires — un `Bcc:` vers un tiers, par exemple. Le sujet vient
 * de l'application, mais la règle ne doit pas dépendre de qui appelle.
 */
function nettoyerEntete(string $valeur): string
{
    return trim(str_replace(["\r", "\n", "\0"], ' ', $valeur));
}

/**
 * Encode un texte d'en-tête selon la RFC 2047 s'il contient du non-ASCII.
 *
 * « Linkee — Réinitialisation de votre mot de passe » partait en UTF-8
 * brut. Un en-tête ne transporte que de l'ASCII : selon le client, le sujet
 * s'affichait avec des caractères de remplacement, et plusieurs filtres
 * anti-spam comptent un en-tête 8 bits comme signal négatif.
 *
 * Base64 plutôt que quoted-printable : sur du français, où presque une
 * lettre sur dix est accentuée, Q produit une suite de « =C3=A9 » plus longue
 * que l'original et illisible dans les journaux.
 */
function encoderEntete(string $texte): string
{
    $texte = nettoyerEntete($texte);

    if (preg_match('/^[\x20-\x7E]*$/', $texte) === 1) {
        return $texte;
    }

    // Découpage en tronçons : un mot encodé ne doit pas dépasser 75
    // caractères, et la césure doit tomber entre deux caractères UTF-8
    // entiers — sinon le client affiche un losange à la jointure.
    $morceaux = [];
    $courant  = '';
    foreach (preg_split('//u', $texte, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $lettre) {
        if (strlen(base64_encode($courant . $lettre)) > 45) {
            $morceaux[] = $courant;
            $courant    = '';
        }
        $courant .= $lettre;
    }
    if ($courant !== '') {
        $morceaux[] = $courant;
    }

    return implode("\r\n ", array_map(
        static fn(string $m): string => '=?UTF-8?B?' . base64_encode($m) . '?=',
        $morceaux
    ));
}

/**
 * Encode un corps de message en quoted-printable.
 *
 * Exigé dès qu'on annonce `Content-Transfer-Encoding: quoted-printable`, et
 * préférable au 8bit : certains relais anciens tronquent les lignes longues
 * ou dégradent les octets hauts, ce qui abîme les accents en chemin.
 */
function encoderCorps(string $corps): string
{
    // Normalise d'abord les fins de ligne : un mélange de \n et \r\n dans un
    // message est un autre signal négatif classique.
    $corps = str_replace(["\r\n", "\r"], "\n", $corps);

    return quoted_printable_encode(str_replace("\n", "\r\n", $corps));
}

/**
 * Un identifiant de message, unique et rattaché au domaine expéditeur.
 *
 * Son absence est relevée par la plupart des filtres. Le domaine doit être
 * celui de l'expéditeur, pas celui de la machine : c'est ce que DMARC
 * regarde pour juger de l'alignement.
 */
function identifiantMessage(string $expediteur): string
{
    $domaine = substr(strrchr($expediteur, '@') ?: '@localhost', 1);

    return sprintf('<%s.%s@%s>', bin2hex(random_bytes(8)), time(), $domaine);
}

/**
 * Les en-têtes d'un message, prêts à être joints.
 *
 * Isolé pour être vérifiable sans rien envoyer : c'est la partie qui décide
 * si un message arrive ou finit en indésirables (tests/Unit/MailTest.php).
 *
 * @param array<string,string|int> $config sortie de configurationMail()
 * @return array<string,string> nom d'en-tête => valeur
 */
function entetesMail(array $config, string $sujet): array
{
    $expediteur = nettoyerEntete((string) $config['expediteur']);
    $nom        = encoderEntete((string) $config['nom']);

    return [
        'Date'                      => date('r'),
        'From'                      => sprintf('%s <%s>', $nom, $expediteur),
        // Répondre à un « noreply » ne mène nulle part, mais l'en-tête doit
        // exister : son absence fait échouer certains contrôles de conformité.
        'Reply-To'                  => $expediteur,
        'Return-Path'               => '<' . nettoyerEntete((string) $config['retour']) . '>',
        'Message-ID'                => identifiantMessage($expediteur),
        'MIME-Version'              => '1.0',
        'Content-Type'              => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => 'quoted-printable',
        // Annonce un message automatique : les répondeurs d'absence savent
        // alors qu'il ne faut pas y répondre, ce qui évite les boucles.
        'Auto-Submitted'            => 'auto-generated',
    ];
}

/** Met les en-têtes au format attendu par mail() et par SMTP. */
function formaterEntetes(array $entetes): string
{
    $lignes = [];
    foreach ($entetes as $nom => $valeur) {
        $lignes[] = $nom . ': ' . $valeur;
    }

    return implode("\r\n", $lignes);
}

/**
 * Envoie un message en texte brut.
 *
 * En local rien ne part : le message est écrit dans le journal, ce qui permet
 * de vérifier son contenu sans configurer de serveur de mail.
 */
function envoyerEmail(string $destinataire, string $sujet, string $corps): bool
{
    if (!filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
        logErreur('Adresse destinataire invalide', null, ['email' => $destinataire]);

        return false;
    }

    $sujet  = nettoyerEntete($sujet);
    $config = configurationMail();

    if (estEnLocal()) {
        logErreur('[mail simulé] ' . $sujet, null, [
            'a'     => $destinataire,
            'corps' => mb_substr(str_replace("\n", ' / ', $corps), 0, 220),
        ]);
        if (PHP_SAPI === 'cli') {
            printf("  [local, non envoyé] %-28s %s\n", $destinataire, $sujet);
        }

        return true;
    }

    $entetes = entetesMail($config, $sujet);
    $corpsQp = encoderCorps($corps);

    try {
        if ($config['transport'] === 'smtp') {
            envoyerParSmtp($config, $destinataire, $sujet, $corpsQp, $entetes);

            return true;
        }

        // Transport `mail()`. Le cinquième paramètre pose l'adresse
        // d'enveloppe : sans lui, SPF est évalué sur le compte d'hébergement
        // et non sur le domaine annoncé, ce qui suffit à faire échouer DMARC.
        // Return-Path est retiré des en-têtes : c'est le MTA qui l'écrit, à
        // partir de cette adresse-là.
        $pourMail = $entetes;
        unset($pourMail['Return-Path']);

        $enveloppe = nettoyerEntete((string) $config['retour']);
        $ok = mail(
            $destinataire,
            encoderEntete($sujet),
            $corpsQp,
            formaterEntetes($pourMail),
            filter_var($enveloppe, FILTER_VALIDATE_EMAIL) ? '-f' . $enveloppe : ''
        );

        if (!$ok) {
            logErreur('Envoi refusé par mail()', null, [
                'a'     => $destinataire,
                'sujet' => $sujet,
            ]);
        }

        return $ok;
    } catch (Throwable $e) {
        // Un envoi qui échoue ne doit jamais faire tomber la page qui l'a
        // déclenché : on trace et on rend false, l'appelant décide.
        logErreur('Envoi du message impossible', $e, ['a' => $destinataire, 'sujet' => $sujet]);

        return false;
    }
}

// ─── Transport SMTP ─────────────────────────────────────────────────────────

/**
 * Lit une réponse du serveur, y compris sur plusieurs lignes.
 *
 * Une réponse multiligne se reconnaît au tiret après le code : « 250-SIZE »
 * annonce une suite, « 250 SIZE » termine. S'arrêter à la première ligne
 * laisserait le reste dans le tampon et décalerait toutes les réponses
 * suivantes — la panne la plus pénible à diagnostiquer d'un client SMTP.
 *
 * @param resource $flux
 */
function smtpLire($flux, string $etape): string
{
    $reponse = '';
    while (($ligne = fgets($flux, 515)) !== false) {
        $reponse .= $ligne;
        if (strlen($ligne) < 4 || $ligne[3] !== '-') {
            break;
        }
    }

    if ($reponse === '') {
        throw new RuntimeException("SMTP : pas de réponse à l'étape « $etape ».");
    }

    return $reponse;
}

/**
 * Envoie une commande et vérifie le code de retour attendu.
 *
 * @param resource   $flux
 * @param list<int>  $codesAttendus
 */
function smtpCommande($flux, ?string $commande, array $codesAttendus, string $etape): string
{
    if ($commande !== null) {
        fwrite($flux, $commande . "\r\n");
    }

    $reponse = smtpLire($flux, $etape);
    $code    = (int) substr($reponse, 0, 3);

    if (!in_array($code, $codesAttendus, true)) {
        // Le mot de passe ne doit jamais apparaître dans un message d'erreur
        // qui partira dans le journal.
        throw new RuntimeException(
            "SMTP : étape « $etape » refusée (" . trim($reponse) . ')'
        );
    }

    return $reponse;
}

/**
 * Remet le message à un serveur SMTP authentifié.
 *
 * C'est le chemin à utiliser en production : le message part par un serveur
 * autorisé à parler pour le domaine, qui le signe en DKIM. `mail()` ne le
 * fera jamais.
 *
 * @param array<string,string|int> $config
 * @param array<string,string>     $entetes
 */
function envoyerParSmtp(array $config, string $destinataire, string $sujet, string $corpsQp, array $entetes): void
{
    $hote = (string) $config['hote'];
    if ($hote === '') {
        throw new RuntimeException('MAIL_TRANSPORT=smtp mais aucun hôte SMTP configuré.');
    }

    $port        = (int) $config['port'];
    $chiffrement = (string) $config['chiffrement'];
    $cible       = ($chiffrement === 'ssl' ? 'ssl://' : '') . $hote . ':' . $port;

    $contexte = stream_context_create([
        'ssl' => [
            // Vérification du certificat : la désactiver rendrait le
            // chiffrement décoratif, un intermédiaire pouvant se présenter à
            // la place du serveur et lire le mot de passe SMTP au passage.
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'allow_self_signed' => false,
        ],
    ]);

    $flux = @stream_socket_client(
        $cible,
        $noErreur,
        $messageErreur,
        15,
        STREAM_CLIENT_CONNECT,
        $contexte
    );

    if ($flux === false) {
        throw new RuntimeException("SMTP : connexion à $cible impossible ($messageErreur).");
    }

    // Sans délai maximal, un serveur qui ne répond plus immobilise le
    // processus PHP jusqu'au timeout global — soit la page entière figée.
    stream_set_timeout($flux, 15);

    try {
        smtpCommande($flux, null, [220], 'accueil');

        // Le nom annoncé doit être celui du domaine expéditeur : un « EHLO
        // localhost » est un motif de rejet fréquent.
        $domaine = substr(strrchr((string) $config['expediteur'], '@') ?: '@localhost', 1);
        smtpCommande($flux, 'EHLO ' . $domaine, [250], 'EHLO');

        if ($chiffrement === 'tls') {
            smtpCommande($flux, 'STARTTLS', [220], 'STARTTLS');
            if (!stream_socket_enable_crypto($flux, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP : passage en TLS refusé.');
            }
            // La négociation TLS invalide tout ce que le serveur avait
            // annoncé en clair : on redemande ses capacités.
            smtpCommande($flux, 'EHLO ' . $domaine, [250], 'EHLO après TLS');
        }

        if ((string) $config['utilisateur'] !== '') {
            smtpCommande($flux, 'AUTH LOGIN', [334], 'AUTH');
            smtpCommande($flux, base64_encode((string) $config['utilisateur']), [334], 'identifiant');
            smtpCommande($flux, base64_encode((string) $config['motdepasse']), [235], 'authentification');
        }

        smtpCommande($flux, 'MAIL FROM:<' . nettoyerEntete((string) $config['retour']) . '>', [250], 'MAIL FROM');
        smtpCommande($flux, 'RCPT TO:<' . $destinataire . '>', [250, 251], 'RCPT TO');
        smtpCommande($flux, 'DATA', [354], 'DATA');

        $entetes['To']      = $destinataire;
        $entetes['Subject'] = encoderEntete($sujet);

        // Un point seul en début de ligne termine le message : il faut le
        // doubler, sinon un corps contenant une telle ligne serait tronqué.
        $corpsProtege = preg_replace('/^\./m', '..', $corpsQp) ?? $corpsQp;

        fwrite($flux, formaterEntetes($entetes) . "\r\n\r\n" . $corpsProtege . "\r\n.\r\n");
        smtpCommande($flux, null, [250], 'remise');

        // QUIT est poli, pas indispensable : si le serveur a déjà accepté le
        // message à l'étape précédente, un échec ici ne le perd pas.
        @fwrite($flux, "QUIT\r\n");
    } finally {
        fclose($flux);
    }
}

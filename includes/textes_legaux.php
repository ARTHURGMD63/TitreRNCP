<?php
/**
 * Les textes légaux de Outsee — une seule source.
 *
 * Mentions légales, CGU, CGV et politique de confidentialité vivaient en HTML dans
 * trois pages du site. L'application mobile doit les afficher elle-même (les
 * boutiques d'applications exigent qu'ils soient lisibles depuis l'app), et ne
 * peut pas en faire un écran à partir de HTML. Le texte vit donc ici, sous
 * forme de données, et sert les deux : api/v1/legal.php pour l'application,
 * legalCorpsHtml() pour les pages du site.
 *
 * Un document :
 *   titre     le titre de la page
 *   maj       date de dernière révision du TEXTE (AAAA-MM-JJ), à changer
 *             chaque fois qu'il change — elle s'affiche telle quelle
 *   sections  liste de { titre?, blocs[] }, la première pouvant n'avoir pas de
 *             titre (introduction)
 *   pied      mentions de fin de page, facultatif
 *
 * Un bloc est { type: 'p', texte } ou { type: 'liste', items[] }. Dans un
 * texte, **ainsi** met en gras : aucun HTML, aucun lien. Les deux
 * consommateurs savent le lire, ce qui n'est pas vrai d'un balisage libre.
 */

/** @return array<string,array<string,mixed>> slug => document */
function textesLegaux(): array
{
    $p     = static fn (string $texte): array => ['type' => 'p', 'texte' => $texte];
    $liste = static fn (string ...$items): array => ['type' => 'liste', 'items' => array_values($items)];

    // L'éditeur : entreprise individuelle d'Arthur Gramond. Le SIREN et
    // l'adresse de l'établissement se renseignent ici dès l'immatriculation
    // (une seule source, reprise par tous les documents).
    $editeur = [
        'nom'     => 'Arthur Gramond',
        'forme'   => 'Entreprise individuelle (EI)',
        'siren'   => 'en cours d’immatriculation',
        'adresse' => 'Clermont-Ferrand (63), adresse de l’établissement communiquée à l’immatriculation',
        'contact' => 'contact@outsee.fr',
    ];
    $identite = "**Outsee** est un service édité par **{$editeur['nom']}**, {$editeur['forme']}.\n"
        . "SIREN : {$editeur['siren']}.\n"
        . "Adresse : {$editeur['adresse']}.\n"
        . "Contact : {$editeur['contact']}";

    $signature = "Outsee — {$editeur['nom']}, {$editeur['forme']}.";

    return [
        'mentions-legales' => [
            'titre'    => 'Mentions légales',
            'maj'      => '2026-11-18',
            'sections' => [
                ['titre' => 'Éditeur', 'blocs' => [
                    $p($identite),
                ]],
                ['titre' => 'Directeur de la publication', 'blocs' => [
                    $p("{$editeur['nom']}, exploitant de l'entreprise individuelle."),
                ]],
                ['titre' => 'Hébergement', 'blocs' => [
                    $liste(
                        "**Application et API :** Render Services, Inc., San Francisco, Californie, États-Unis (render.com).",
                        "**Base de données :** TiDB Cloud, PingCAP (pingcap.com).",
                        "**Nom de domaine et messagerie :** OVH SAS, 2 rue Kellermann, 59100 Roubaix, France (ovhcloud.com)."
                    ),
                ]],
                ['titre' => 'Propriété intellectuelle', 'blocs' => [
                    $p("L'ensemble des contenus présents sur le site et dans l'application (textes, images, logo, design) est la propriété de l'éditeur ou de ses partenaires. Toute reproduction sans autorisation écrite est interdite."),
                ]],
                ['titre' => 'Responsabilité', 'blocs' => [
                    $p("Les informations diffusées par les établissements (événements, tarifs, horaires) sont publiées sous leur responsabilité. L'éditeur ne peut être tenu responsable d'inexactitudes ou d'événements annulés."),
                ]],
                ['titre' => 'Droit applicable', 'blocs' => [
                    $p("Les présentes mentions légales sont soumises au droit français."),
                ]],
            ],
        ],

        'cgu' => [
            'titre'    => "Conditions Générales d'Utilisation",
            'maj'      => '2026-11-18',
            'sections' => [
                ['titre' => 'Article 1 — Objet', 'blocs' => [
                    $p("Les présentes CGU régissent l'usage de la plateforme Outsee, éditée par {$editeur['nom']} ({$editeur['forme']}), qui permet aux étudiants de découvrir des événements, de bénéficier de réductions et de se regrouper pour des activités sportives ou sociales. Les services payants proposés aux établissements relèvent des Conditions Générales de Vente."),
                ]],
                ['titre' => 'Article 2 — Inscription', 'blocs' => [
                    $p("L'inscription est gratuite et réservée aux personnes majeures et aux établissements. L'utilisateur s'engage à fournir des informations exactes et à maintenir la confidentialité de son mot de passe."),
                ]],
                ['titre' => 'Article 3 — Utilisation de la plateforme', 'blocs' => [
                    $p("L'utilisateur s'interdit de :"),
                    $liste(
                        "Usurper l'identité d'une autre personne",
                        "Publier des contenus illégaux, offensants ou discriminatoires",
                        "Exploiter commercialement les données des autres utilisateurs",
                        "Tenter d'accéder à des zones non autorisées du service"
                    ),
                ]],
                ['titre' => 'Article 4 — Réductions et passes', 'blocs' => [
                    $p("Les réductions proposées par les établissements sont valables uniquement sur présentation du QR code lors du check-in. Outsee ne garantit pas la disponibilité des offres et décline toute responsabilité en cas de refus par l'établissement."),
                ]],
                ['titre' => "Article 5 — Responsabilité de l'utilisateur", 'blocs' => [
                    $p("L'utilisateur est seul responsable de son comportement au sein des événements et des activités. Outsee n'est pas partie prenante des rencontres physiques organisées via la plateforme."),
                ]],
                ['titre' => 'Article 6 — Suspension de compte', 'blocs' => [
                    $p("L'éditeur peut suspendre ou supprimer un compte en cas de manquement aux présentes CGU, après en avoir informé l'utilisateur, sauf urgence (contenu illicite, atteinte à la sécurité d'autrui)."),
                ]],
                ['titre' => 'Article 7 — Modification des CGU', 'blocs' => [
                    $p("Les présentes CGU peuvent évoluer. Les utilisateurs sont informés de toute modification importante sur la plateforme avant son entrée en vigueur."),
                ]],
                ['titre' => 'Article 8 — Litiges', 'blocs' => [
                    $p("Les présentes CGU sont soumises au droit français. En cas de litige, une solution amiable est recherchée en priorité (contact : {$editeur['contact']}). L'utilisateur consommateur peut recourir gratuitement à un médiateur de la consommation. À défaut, les tribunaux compétents sont ceux désignés par la loi."),
                ]],
            ],
            'pied' => $signature,
        ],

        'cgv' => [
            'titre'    => 'Conditions Générales de Vente',
            'maj'      => '2026-11-18',
            'sections' => [
                ['titre' => 'Article 1 — Objet et champ', 'blocs' => [
                    $p("Les présentes CGV régissent la vente, par {$editeur['nom']} ({$editeur['forme']}, SIREN {$editeur['siren']}), des services payants d'Outsee aux établissements professionnels et associations (bars, clubs, BDE, organisateurs) : abonnements, événements au-delà du quota mensuel et mises en avant. Elles sont acceptées lors de la souscription sur le site. Ces services ne sont pas vendus dans l'application mobile."),
                ]],
                ['titre' => 'Article 2 — Prix', 'blocs' => [
                    $p("Les prix sont ceux affichés sur le site au moment de la commande. Ils sont exprimés hors taxes. La TVA est appliquée au taux en vigueur, sauf franchise en base de TVA de l'éditeur, auquel cas la facture porte la mention « TVA non applicable, article 293 B du CGI »."),
                ]],
                ['titre' => 'Article 3 — Abonnement', 'blocs' => [
                    $liste(
                        "L'abonnement est mensuel et reconduit tacitement chaque mois.",
                        "Il est payable d'avance, par carte bancaire, via le prestataire de paiement Stripe.",
                        "La période offerte éventuellement associée à une formule est indiquée lors de la souscription ; aucun montant n'est prélevé pendant celle-ci.",
                        "Le changement de formule prend effet immédiatement, au prorata du mois en cours."
                    ),
                ]],
                ['titre' => 'Article 4 — Résiliation', 'blocs' => [
                    $p("L'établissement peut résilier à tout moment depuis son espace (gestion de l'abonnement) ou en supprimant son compte. La résiliation prend effet à la fin de la période déjà payée, sauf suppression du compte, qui l'arrête immédiatement. Les services ponctuels déjà consommés (événement supplémentaire publié, mise en avant lancée) restent dus et ne sont pas remboursés."),
                ]],
                ['titre' => 'Article 5 — Services ponctuels', 'blocs' => [
                    $p("Un événement publié au-delà du quota mensuel de la formule, ou une mise en avant, est payé au moment de sa publication. Le service est exécuté dès la publication : l'établissement, qui agit à titre professionnel, ne bénéficie pas d'un droit de rétractation."),
                ]],
                ['titre' => 'Article 6 — Retard et défaut de paiement', 'blocs' => [
                    $p("En cas de paiement refusé, le prestataire de paiement procède à de nouvelles tentatives. Faute de régularisation, la formule est suspendue. Conformément à l'article L441-10 du Code de commerce, tout retard de paiement entraîne des pénalités au taux de trois fois le taux d'intérêt légal, ainsi qu'une indemnité forfaitaire pour frais de recouvrement de 40 euros."),
                ]],
                ['titre' => "Article 7 — Obligations de l'éditeur", 'blocs' => [
                    $p("L'éditeur met en œuvre les moyens raisonnables pour assurer la disponibilité du service. Il est tenu à une obligation de moyens et ne garantit ni un nombre d'inscrits ni un chiffre d'affaires. Sa responsabilité est limitée aux sommes payées par l'établissement au cours des douze derniers mois."),
                ]],
                ['titre' => 'Article 8 — Contenus publiés', 'blocs' => [
                    $p("L'établissement est seul responsable des événements, tarifs, réductions et visuels qu'il publie, et garantit en détenir les droits. L'éditeur peut retirer un contenu manifestement illicite ou contraire aux CGU."),
                ]],
                ['titre' => 'Article 9 — Données personnelles', 'blocs' => [
                    $p("L'établissement accède aux données des étudiants inscrits à ses événements pour la seule gestion de ces événements (accueil, contrôle à l'entrée). Il s'interdit toute autre utilisation, notamment commerciale, et toute cession à des tiers. Voir la politique de confidentialité."),
                ]],
                ['titre' => 'Article 10 — Droit applicable et litiges', 'blocs' => [
                    $p("Les présentes CGV sont soumises au droit français. À défaut d'accord amiable, tout litige entre professionnels relève des tribunaux compétents du ressort de Clermont-Ferrand."),
                ]],
            ],
            'pied' => $signature,
        ],

        'confidentialite' => [
            'titre'    => 'Politique de confidentialité',
            'maj'      => '2026-11-18',
            'sections' => [
                ['blocs' => [
                    $p("Cette politique explique quelles données Outsee collecte, pourquoi, avec qui elles sont partagées et combien de temps elles sont gardées, conformément au Règlement Général sur la Protection des Données (RGPD)."),
                ]],
                ['titre' => '1. Responsable du traitement', 'blocs' => [
                    $p($identite),
                ]],
                ['titre' => '2. Données collectées', 'blocs' => [
                    $liste(
                        "**Identité et compte :** nom, prénom, e-mail, mot de passe (haché), date de naissance (vérification de la majorité), ville, photo de profil facultative",
                        "**Profil étudiant :** école, promotion, centres d'intérêt",
                        "**Activité :** inscriptions aux événements et présence (scan du pass), activités sportives et culturelles, abonnements, avis, photos de soirée publiées",
                        "**Position :** utilisée au moment d'une recherche par distance, si tu l'autorises ; elle n'est pas enregistrée dans ton compte",
                        "**Caméra :** utilisée par les établissements pour scanner les pass ; aucune image n'est conservée",
                        "**Notifications :** identifiant de notification de ton téléphone, si tu les actives",
                        "**Établissements :** coordonnées du responsable et informations de facturation (le paiement par carte est traité par Stripe ; Outsee ne voit jamais le numéro de carte)",
                        "**Techniques :** adresse IP, journaux de connexion et d'erreurs"
                    ),
                ]],
                ['titre' => '3. Finalités et bases légales', 'blocs' => [
                    $liste(
                        "Gérer ton compte, t'authentifier, sécuriser le service : exécution du contrat (CGU) et intérêt légitime (sécurité)",
                        "Te proposer des événements et des personnes pertinents, gérer tes inscriptions et le check-in : exécution du contrat",
                        "T'envoyer des notifications : consentement, retirable dans les réglages du téléphone",
                        "Facturer les établissements : exécution du contrat (CGV) et obligation légale (comptabilité)",
                        "Fournir aux établissements des statistiques de fréquentation agrégées : intérêt légitime"
                    ),
                ]],
                ['titre' => '4. Destinataires et sous-traitants', 'blocs' => [
                    $p("Tes données sont accessibles à l'éditeur, et :"),
                    $liste(
                        "**Les établissements**, pour les événements auxquels tu t'inscris : prénom, nom, école, promotion et présence, pour la seule gestion de l'événement",
                        "**Render** (hébergement de l'application, États-Unis) et **TiDB Cloud / PingCAP** (base de données)",
                        "**OVH** (messagerie électronique, France)",
                        "**Stripe** (paiement des établissements)",
                        "**Expo, Google Firebase et Apple** (acheminement des notifications)",
                        "**Google Maps** (carte sur Android) et **OpenStreetMap / Nominatim** (conversion des adresses des établissements)"
                    ),
                    $p("Certains de ces prestataires sont situés hors de l'Union européenne, notamment aux États-Unis. Ces transferts sont encadrés par les clauses contractuelles types de la Commission européenne ou par le cadre de protection des données UE–États-Unis. Aucune donnée n'est vendue."),
                ]],
                ['titre' => '5. Durées de conservation', 'blocs' => [
                    $liste(
                        "**Compte :** tant qu'il est actif ; à sa suppression, les données personnelles sont effacées immédiatement (voir point 6)",
                        "**Session dans l'application :** 30 jours après la dernière utilisation (1 jour pour un administrateur)",
                        "**Tentatives de connexion :** 15 minutes",
                        "**Historique des notifications envoyées :** 30 jours ; téléphone inactif : 180 jours",
                        "**Paiements non aboutis :** 90 jours",
                        "**Pièces comptables (factures, paiements des établissements) :** 10 ans, obligation légale",
                        "**Journaux techniques :** 8 semaines"
                    ),
                ]],
                ['titre' => '6. Tes droits', 'blocs' => [
                    $p("Tu disposes d'un droit d'accès, de rectification, d'effacement, de limitation, de portabilité et d'opposition, ainsi que du droit de définir des directives sur le sort de tes données après ton décès. Pour les exercer : {$editeur['contact']}. Une réponse t'est apportée sous un mois."),
                    $p("**Supprimer ton compte** : depuis l'application, dans « Moi », « Supprimer mon compte ». Tes informations personnelles (nom, e-mail, photo, ville, date de naissance, centres d'intérêt, abonnements, activités, avis, photos de soirée, appareils) sont effacées immédiatement. Seules sont conservées, sans ton nom, tes présences passées avec ton école et ta promotion, pour que les statistiques des établissements restent exactes."),
                    $p("Tu peux aussi adresser une réclamation à la CNIL (cnil.fr)."),
                ]],
                ['titre' => '7. Cookies et stockage', 'blocs' => [
                    $p("Le site utilise uniquement un cookie de session pour maintenir la connexion des établissements. Aucun cookie publicitaire ou de mesure d'audience n'est déposé. L'application garde sur ton téléphone ton jeton de connexion (dans le trousseau sécurisé) et une copie temporaire de quelques écrans (ton pass, ton profil) pour fonctionner sans réseau ; elle est effacée à la déconnexion."),
                ]],
                ['titre' => '8. Sécurité', 'blocs' => [
                    $p("Les mots de passe sont hachés (bcrypt). Les échanges sont chiffrés en HTTPS. Les sessions sont régénérées à chaque connexion, et les connexions de l'application sont révoquées au changement de mot de passe."),
                ]],
            ],
            'pied' => $signature,
        ],
    ];
}

/**
 * Un texte à balisage minimal → HTML sûr.
 *
 * Le texte est d'abord échappé, puis seuls **gras** et les retours à la ligne
 * deviennent des balises : rien de ce qui est écrit dans les données ne peut
 * donc introduire de HTML.
 */
function legalInlineHtml(string $texte): string
{
    $html = htmlspecialchars($texte, ENT_QUOTES, 'UTF-8');
    $html = (string) preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);

    return nl2br($html, false);
}

/** Date AAAA-MM-JJ → « 14/10/2026 ». */
function legalDateFr(string $iso): string
{
    $t = strtotime($iso);

    return $t === false ? $iso : date('d/m/Y', $t);
}

/**
 * Le corps d'un document (sections et pied), prêt à être inséré dans la page.
 *
 * @param array<string,mixed> $doc un élément de textesLegaux()
 */
function legalCorpsHtml(array $doc): string
{
    $sortie = '';

    foreach ($doc['sections'] as $section) {
        if (!empty($section['titre'])) {
            $sortie .= '<h2>' . htmlspecialchars((string) $section['titre'], ENT_QUOTES, 'UTF-8') . "</h2>\n";
        }
        foreach ($section['blocs'] as $bloc) {
            if ($bloc['type'] === 'liste') {
                $sortie .= "<ul>\n";
                foreach ($bloc['items'] as $item) {
                    $sortie .= '<li>' . legalInlineHtml((string) $item) . "</li>\n";
                }
                $sortie .= "</ul>\n";
            } else {
                $sortie .= '<p>' . legalInlineHtml((string) $bloc['texte']) . "</p>\n";
            }
        }
    }

    $pied = !empty($doc['pied']) ? htmlspecialchars((string) $doc['pied'], ENT_QUOTES, 'UTF-8') . '<br>' : '';

    return $sortie
        . '<p style="margin-top:40px;font-size:var(--fs-2);color:var(--gris);">'
        . $pied . 'Dernière mise à jour : ' . legalDateFr((string) $doc['maj']) . "</p>\n";
}

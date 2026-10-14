<?php
/**
 * Les textes légaux de Linkee — une seule source.
 *
 * Mentions légales, CGU et politique de confidentialité vivaient en HTML dans
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

    $signature = "Document réalisé à Clermont-Ferrand par Arthur Gramond, étudiant en B2 à Hesias.";

    return [
        'mentions-legales' => [
            'titre'    => 'Mentions légales',
            'maj'      => '2026-10-14',
            'sections' => [
                ['titre' => 'Éditeur du site', 'blocs' => [
                    $p("**Linkee** — plateforme de mise en relation étudiants et établissements.\n"
                        . "Projet réalisé à Clermont-Ferrand par **Arthur Gramond**, étudiant en deuxième année (B2) à **Hesias**, "
                        . "dans le cadre du Titre professionnel Développeur Web et Web Mobile.\n"
                        . "Contact : contact@linkee.fr"),
                ]],
                ['titre' => 'Hébergement', 'blocs' => [
                    $p("Application hébergée en environnement local (serveur WAMP), Clermont-Ferrand."),
                ]],
                ['titre' => 'Directeur de la publication', 'blocs' => [
                    $p("Arthur Gramond — étudiant en B2 à Hesias, Clermont-Ferrand."),
                ]],
                ['titre' => 'Propriété intellectuelle', 'blocs' => [
                    $p("L'ensemble des contenus présents sur ce site (textes, images, logo, design) est la propriété de Linkee ou de ses partenaires. Toute reproduction sans autorisation écrite est interdite."),
                ]],
                ['titre' => 'Responsabilité', 'blocs' => [
                    $p("Les informations diffusées par les partenaires (événements, tarifs, horaires) sont publiées sous leur responsabilité. Linkee ne peut être tenu responsable d'inexactitudes ou d'événements annulés."),
                ]],
                ['titre' => 'Droit applicable', 'blocs' => [
                    $p("Les présentes mentions légales sont soumises au droit français."),
                ]],
            ],
        ],

        'cgu' => [
            'titre'    => "Conditions Générales d'Utilisation",
            'maj'      => '2026-10-14',
            'sections' => [
                ['titre' => 'Article 1 — Objet', 'blocs' => [
                    $p("Les présentes CGU régissent l'usage de la plateforme Linkee, qui permet aux étudiants de découvrir des événements, bénéficier de réductions et se regrouper pour des activités sportives ou sociales."),
                ]],
                ['titre' => 'Article 2 — Inscription', 'blocs' => [
                    $p("L'inscription est gratuite et réservée aux étudiants majeurs ou aux établissements partenaires. L'utilisateur s'engage à fournir des informations exactes et à maintenir la confidentialité de son mot de passe."),
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
                    $p("Les réductions proposées par les partenaires sont valables uniquement sur présentation du QR code lors du check-in. Linkee ne garantit pas la disponibilité des offres et décline toute responsabilité en cas de refus par l'établissement."),
                ]],
                ['titre' => "Article 5 — Responsabilité de l'utilisateur", 'blocs' => [
                    $p("L'utilisateur est seul responsable de son comportement au sein des événements et des squads. Linkee n'est pas partie prenante des interactions physiques organisées via la plateforme."),
                ]],
                ['titre' => 'Article 6 — Suspension de compte', 'blocs' => [
                    $p("Linkee se réserve le droit de suspendre ou supprimer tout compte en cas de non-respect des présentes CGU, sans préavis ni justification."),
                ]],
                ['titre' => 'Article 7 — Modification des CGU', 'blocs' => [
                    $p("Linkee peut modifier les présentes CGU à tout moment. Les utilisateurs seront informés par notification sur la plateforme."),
                ]],
                ['titre' => 'Article 8 — Litiges', 'blocs' => [
                    $p("Tout litige relatif à l'utilisation de Linkee sera soumis au droit français et à la compétence des tribunaux de Clermont-Ferrand."),
                ]],
            ],
            'pied' => $signature,
        ],

        'confidentialite' => [
            'titre'    => 'Politique de confidentialité',
            'maj'      => '2026-10-14',
            'sections' => [
                ['blocs' => [
                    $p("Chez Linkee, nous prenons la protection de tes données personnelles très au sérieux. Cette politique explique comment nous collectons, utilisons et protégeons tes informations, conformément au Règlement Général sur la Protection des Données (RGPD)."),
                ]],
                ['titre' => '1. Données collectées', 'blocs' => [
                    $liste(
                        "**Identité :** nom, prénom, email",
                        "**Profil étudiant :** école, promotion, centres d'intérêt",
                        "**Activité :** inscriptions aux events, squads rejointes, abonnements",
                        "**Techniques :** adresse IP, logs de connexion"
                    ),
                ]],
                ['titre' => '2. Finalités', 'blocs' => [
                    $p("Tes données sont utilisées pour :"),
                    $liste(
                        "Gérer ton compte et t'authentifier",
                        "Te proposer des events et personnes pertinents",
                        "Permettre le check-in aux événements via QR code",
                        "Fournir aux partenaires des statistiques anonymisées"
                    ),
                ]],
                ['titre' => '3. Base légale', 'blocs' => [
                    $p("Le traitement repose sur ton consentement (inscription) et sur l'exécution du contrat (utilisation du service)."),
                ]],
                ['titre' => '4. Durée de conservation', 'blocs' => [
                    $p("Tes données sont conservées tant que ton compte est actif. En cas de suppression, elles sont effacées sous 30 jours (sauf obligations légales)."),
                ]],
                ['titre' => '5. Destinataires', 'blocs' => [
                    $p("Tes données sont accessibles uniquement par :"),
                    $liste(
                        "L'équipe Linkee",
                        "Les partenaires pour les events auxquels tu t'inscris (prénom, nom, école, promo)",
                        "L'hébergeur (serveur local, Clermont-Ferrand)"
                    ),
                    $p("Aucune donnée n'est vendue à des tiers."),
                ]],
                ['titre' => '6. Tes droits (RGPD)', 'blocs' => [
                    $p("Tu disposes d'un droit d'accès, de rectification, d'effacement, de limitation, de portabilité et d'opposition. Pour les exercer : contact@linkee.fr"),
                    $p("**Supprimer ton compte** : depuis l'application, dans « Moi », « Supprimer mon compte ». Tes informations personnelles (nom, email, photo, ville, date de naissance, centres d'intérêt, abonnements, squads, avis, photos de soirée) sont effacées immédiatement. Seules sont conservées, sans ton nom, tes présences passées avec ton école et ta promotion, pour que les statistiques des partenaires restent exactes."),
                ]],
                ['titre' => '7. Cookies', 'blocs' => [
                    $p("Linkee utilise uniquement un cookie de session pour te maintenir connecté. Aucun cookie publicitaire ou de tracking n'est déposé."),
                ]],
                ['titre' => '8. Sécurité', 'blocs' => [
                    $p("Les mots de passe sont hachés avec bcrypt. Les échanges sont chiffrés en HTTPS. Les sessions sont régénérées à chaque connexion pour éviter les détournements."),
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

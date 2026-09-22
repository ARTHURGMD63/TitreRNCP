<?php
/**
 * Coquille du back-office fondateurs.
 *
 * Reprend le gabarit partenaire (.partner-shell, .sidebar-link…) plutôt que
 * d'inventer un troisième système : un back-office interne n'a aucune raison
 * d'avoir sa propre grammaire visuelle, et une coquille de plus serait une
 * coquille de plus à maintenir. Seule la barre latérale change de contenu.
 *
 * Les fonctions de rendu échappent tout ce qu'elles reçoivent. Le back-office
 * affiche des noms d'établissements et des contacts saisis ailleurs : c'est
 * précisément le genre d'écran où une injection passe inaperçue, puisque
 * personne d'extérieur ne le regarde.
 */

require_once __DIR__ . '/crm.php';

/**
 * Ouvre la page : <head>, coquille, barre latérale, début du contenu.
 *
 * `$pdo` est passé explicitement plutôt que récupéré en `global` : une
 * fonction de rendu qui va chercher sa connexion dans la portée globale
 * fonctionne jusqu'au jour où on l'appelle depuis un contexte où elle n'y
 * est pas, et échoue alors sans rien dire.
 */
function adminHeader(PDO $pdo, string $page, string $titre, string $surtitre = 'Back-office'): void
{
    $moi = currentUser();

    // Libellé, fichier, tracé SVG.
    $entrees = [
        'accueil'      => ['Tableau de bord', 'index.php',        'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z'],
        'clients'      => ['Clients',         'clients.php',      'M3 21V9l9-6 9 6v12M9 21v-6h6v6'],
        'utilisateurs' => ['Étudiants',       'utilisateurs.php', 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 3a4 4 0 1 1 0 8 4 4 0 0 1 0-8M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75'],
        'evenements'   => ['Événements',      'evenements.php',   'M3 4h18v18H3zM16 2v4M8 2v4M3 10h18'],
        'finances'     => ['Finances',        'finances.php',     'M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'],
        'moderation'   => ['Modération',      'moderation.php',   'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z'],
    ];

    // La pastille de modération doit se voir depuis n'importe quelle page,
    // pas seulement depuis la sienne : un signalement non traité est une
    // obligation de délai, pas une notification parmi d'autres.
    $nbSignalements = crmSignalementsEnAttente($pdo);
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudentLink — <?= htmlspecialchars($titre) ?></title>
<?= themeBootScript() ?>
<?= metaCsrf() ?>
<link rel="stylesheet" href="<?= asset('/assets/css/style.css') ?>">
<link rel="icon" type="image/png" href="<?= baseUrl('/Logo.png') ?>">
</head>
<body>
<a href="#contenu" class="skip-nav">Aller au contenu</a>
<div class="partner-shell">

  <aside class="partner-sidebar">
    <div class="sidebar-brand">
      <div style="font-family:var(--font-sans);font-weight:var(--fw-bold);font-size:var(--fs-5);color:#fff;">
        StudentLink <em style="font-style:italic;color:var(--rouge);">/ Interne</em>
      </div>
    </div>
    <nav class="sidebar-nav" aria-label="Navigation du back-office">
      <?php foreach ($entrees as $code => [$libelle, $fichier, $trace]): ?>
        <a href="<?= baseUrl('/admin/' . $fichier) ?>"
           class="sidebar-link <?= $page === $code ? 'active' : '' ?>"
           <?= $page === $code ? 'aria-current="page"' : '' ?>>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="<?= $trace ?>"/>
          </svg>
          <?= htmlspecialchars($libelle) ?>
          <?php if ($code === 'moderation' && $nbSignalements > 0): ?>
            <span style="margin-left:auto;background:var(--rouge);color:#fff;border-radius:var(--radius-pill);
                         font-size:var(--fs-1);font-weight:var(--fw-bold);padding:1px 8px;">
              <?= $nbSignalements ?><span class="sr-only"> signalements à traiter</span>
            </span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-venue">
      <div class="sidebar-venue-name"><?= htmlspecialchars(trim(($moi['prenom'] ?? '') . ' ' . ($moi['nom'] ?? ''))) ?></div>
      <div class="sidebar-venue-city">Fondateur</div>
      <a href="<?= baseUrl('/auth/logout.php') ?>" class="lien-action"
         style="margin-top:12px;display:inline-block;font-size:var(--fs-2);color:rgba(255,255,255,0.45);text-decoration:none;">→ Déconnexion</a>
    </div>
  </aside>

  <main class="partner-main" id="contenu">
    <div class="partner-header">
      <div class="event-label"><?= htmlspecialchars($surtitre) ?></div>
      <h1 class="partner-headline" style="font-size:var(--fs-8);"><?= htmlspecialchars($titre) ?></h1>
    </div>
    <?= csrfFlash() ?>
<?php
}

function adminFooter(): void
{
    ?>
  </main>
</div>
<script src="<?= asset('/assets/js/app.js') ?>"></script>
</body>
</html>
<?php
}

/**
 * Une tuile de chiffre, en liste de définition : le libellé et la valeur
 * forment une paire, et un lecteur d'écran les annonce comme telle au lieu
 * de lire deux fragments sans lien.
 *
 * `$ton` ∈ neutre | succes | alerte | danger | marque.
 */
function adminTuile(string $label, string $valeur, string $sous = '', string $ton = 'neutre'): void
{
    $tons = ['neutre', 'succes', 'alerte', 'danger', 'marque'];
    $classe = in_array($ton, $tons, true) && $ton !== 'neutre' ? ' est-' . $ton : '';
    ?>
    <dl class="admin-tuile<?= $classe ?>">
      <dt><?= htmlspecialchars($label) ?></dt>
      <dd><?= htmlspecialchars($valeur) ?></dd>
      <?php if ($sous !== ''): ?>
        <dd class="admin-tuile-sous"><?= htmlspecialchars($sous) ?></dd>
      <?php endif; ?>
    </dl>
    <?php
}

/** Pastille de statut : la couleur accompagne le mot, elle ne le remplace pas. */
function adminPastille(string $texte, string $couleur): string
{
    // `$couleur` vient toujours du vocabulaire de crm.php, jamais d'une
    // saisie : on la borne quand même à une expression de variable CSS,
    // pour qu'aucun appel futur ne puisse refermer l'attribut style.
    $sure = preg_match('/^var\(--[a-z0-9-]+\)$/', $couleur) ? $couleur : 'var(--gris)';

    return '<span class="admin-pastille" style="color:' . $sure . ';">'
         . htmlspecialchars($texte) . '</span>';
}

/**
 * Accord du pluriel.
 *
 * Ecrit à la main, ça donne « 1 payants » et « 1 prospects à convertir » :
 * une faute d'accord sur un tableau de bord qu'on relit chaque lundi finit
 * par se voir.
 */
function pluriel(int $n, string $mot, string $suffixe = 's'): string
{
    return $n > 1 ? $mot . $suffixe : $mot;
}

/**
 * Montant en euros. Espace insécable avant le symbole, comme le veut la
 * typographie française : « 1 049 € » ne doit pas se couper en fin de ligne.
 */
function eur(float $montant, int $decimales = 0): string
{
    return number_format($montant, $decimales, ',', "\u{202F}") . "\u{00A0}€";
}

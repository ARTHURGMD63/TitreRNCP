<?php
/**
 * Créer ou modifier un événement depuis l'espace pro web.
 *
 * Une seule fonction de traitement pour la création et la modification
 * (partenaire/create_event.php et edit_event.php en étaient deux copies), et
 * les mêmes règles que api/v1/partenaire_evenement.php :
 *   - le type découle du compte (typeEvenementPour) ;
 *   - quotas d'offres flash revérifiés à l'enregistrement ;
 *   - mise en avant résolue par resoudreSponsoring(), tarif jamais lu du
 *     formulaire ;
 *   - tout ce qui est consommé s'inscrit au registre des achats.
 *
 * Différence assumée avec l'application : c'est ICI qu'un établissement qui
 * a épuisé son quota publie un événement supplémentaire, en acceptant
 * explicitement son prix. Les achats passent par le site, jamais par
 * l'application (règles des stores).
 */

require_once __DIR__ . '/crm.php';
require_once __DIR__ . '/comptes.php';
require_once __DIR__ . '/sponsoring.php';
require_once __DIR__ . '/capacites.php';
require_once __DIR__ . '/evenements_temps.php';
require_once __DIR__ . '/musique.php';
require_once __DIR__ . '/temps_reel.php';

/** Une date ISO en valeur de champ datetime-local. */
function valeurDateLocale(?string $date): string
{
    return $date ? date('Y-m-d\TH:i', (int) strtotime($date)) : '';
}

/**
 * Valide et enregistre le formulaire. Redirige vers la liste en cas de
 * succès ; sinon renvoie les erreurs et les valeurs saisies.
 *
 * @param array<string, mixed>      $etab
 * @param array<string, mixed>      $capacites
 * @param array<string, mixed>|null $existant l'événement modifié, null à la création
 * @return array{erreurs: list<string>, valeurs: array<string, mixed>}
 */
function traiterFormulaireEvenement(PDO $pdo, array $etab, array $capacites, ?array $existant): array
{
    $etabId  = (int) $etab['id'];
    $p       = static fn (string $cle): string => trim((string) ($_POST[$cle] ?? ''));
    $erreurs = [];

    $v = [
        'titre'         => mb_substr($p('titre'), 0, 255),
        'description'   => $p('description'),
        'type'          => typeEvenementPour((string) ($etab['type'] ?? ''), $p('type')) ?? '',
        'style_musique' => $p('style_musique'),
        'date_heure'    => $p('date_heure'),
        'date_fin'      => $p('date_fin') ?: null,
        'quota'         => (int) ($_POST['quota'] ?? 0),
        'reduction'     => max(0, min(100, (int) ($_POST['reduction'] ?? 0))),
        'prix_normal'   => max(0, (float) str_replace(',', '.', $p('prix_normal'))),
        'is_gratuit'    => isset($_POST['is_gratuit']) ? 1 : 0,
        'is_flash'      => isset($_POST['is_flash']) ? 1 : 0,
        'flash_expiry'  => isset($_POST['is_flash']) ? ($p('flash_expiry') ?: null) : null,
        'lieu'          => mb_substr($p('lieu'), 0, 255),
        'is_sponsorise' => isset($_POST['is_sponsorise']) ? 1 : 0,
        'sponsor_formule' => isset($_POST['is_sponsorise']) ? ($p('sponsor_formule') ?: null) : null,
    ];
    $accepteSupplement = !empty($_POST['accepte_supplement']);

    if ($v['titre'] === '') {
        $erreurs[] = 'Donnez un titre à l\'événement.';
    }
    if ($v['type'] === '' || $v['type'] === 'sport') {
        $erreurs[] = 'Type d\'événement invalide pour ce compte.';
    }
    if ($v['style_musique'] !== '' && !styleMusiqueValide($v['style_musique'])) {
        $erreurs[] = 'Style de musique invalide.';
    }
    if ($v['date_heure'] === '' || strtotime($v['date_heure']) === false) {
        $erreurs[] = 'Indiquez la date et l\'heure de début.';
    } elseif (evenementFinEffective($v['date_heure'], $v['date_fin']) <= time() && $existant === null) {
        $erreurs[] = 'Cet événement serait déjà terminé : choisissez une date à venir.';
    }
    if ($v['date_fin'] && $v['date_heure'] !== '' && strtotime($v['date_fin']) <= strtotime($v['date_heure'])) {
        $erreurs[] = 'La fin doit être après le début.';
    }
    if ($v['quota'] < 1 || $v['quota'] > 10000) {
        $erreurs[] = 'Le nombre de places doit être compris entre 1 et 10 000.';
    }
    if ($v['is_flash'] && !$v['flash_expiry']) {
        $erreurs[] = 'Indiquez jusqu\'à quand court l\'offre flash.';
    }
    if ($v['is_flash'] && !flashDisponible($pdo, $etabId, $capacites, $existant ? (int) $existant['id'] : null)) {
        $erreurs[] = 'Votre quota d\'offres flash du mois est atteint (' . (int) $capacites['flash_par_mois'] . ' par mois).';
    }
    if ($v['is_sponsorise'] && !formuleSponsoringValide($v['sponsor_formule'])) {
        $erreurs[] = 'Choisissez une formule de mise en avant.';
    }

    // Événement au-delà du quota : seulement à la création, et seulement si le
    // prix a été accepté noir sur blanc.
    $supplement = 0.0;
    if ($existant === null && evenementSeraSupplementaire($pdo, $etabId, $capacites)) {
        if (!$accepteSupplement) {
            $erreurs[] = 'Votre quota d\'événements du mois est atteint : cochez l\'acceptation du supplément de '
                       . eurosSite((float) PRIX_EVENEMENT_SUPPLEMENTAIRE) . ' HT pour publier celui-ci.';
        }
        $supplement = (float) PRIX_EVENEMENT_SUPPLEMENTAIRE;
    }

    $sponso = null;
    if (!$erreurs) {
        $sponso = resoudreSponsoring($pdo, $etabId, $capacites, $existant, (bool) $v['is_sponsorise'], $v['sponsor_formule'], $v['date_heure'], $v['date_fin']);
        if ($sponso['erreur'] !== null) {
            $erreurs[] = $sponso['erreur'];
        }
    }

    if ($erreurs || $sponso === null) {
        return ['erreurs' => $erreurs, 'valeurs' => $v + ['accepte_supplement' => $accepteSupplement]];
    }

    $commun = [
        $v['titre'], $v['description'], $v['type'], $v['style_musique'] ?: null, $v['date_heure'], $v['date_fin'],
        $v['quota'], $v['reduction'], $v['prix_normal'], $v['is_flash'], $v['flash_expiry'], $v['is_gratuit'], $v['lieu'],
        $sponso['is_sponsorise'], $sponso['formule'], $sponso['tarif'], $sponso['fin'],
    ];

    $pdo->beginTransaction();
    try {
        // Verrou de l'établissement : deux publications simultanées ne lisent
        // pas toutes deux « il reste une place dans le quota ».
        verrouillerEtablissement($pdo, $etabId);

        if ($existant === null) {
            $pdo->prepare(
                'INSERT INTO evenements (titre, description, type, style_musique, date_heure, date_fin, quota, reduction, prix_normal,
                                         is_flash, flash_expiry, is_gratuit, lieu, is_sponsorise, sponsor_formule, sponsor_tarif, sponsor_jusqu_au,
                                         supplement_tarif, etablissement_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([...$commun, $supplement, $etabId]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $id = (int) $existant['id'];
            $pdo->prepare(
                'UPDATE evenements SET titre=?, description=?, type=?, style_musique=?, date_heure=?, date_fin=?, quota=?, reduction=?, prix_normal=?,
                                       is_flash=?, flash_expiry=?, is_gratuit=?, lieu=?, is_sponsorise=?, sponsor_formule=?, sponsor_tarif=?, sponsor_jusqu_au=?
                  WHERE id = ? AND etablissement_id = ?'
            )->execute([...$commun, $id, $etabId]);
        }

        enregistrerAchatsEvenement($pdo, $etabId, $id, $existant, [
            'titre' => $v['titre'], 'supplement_tarif' => $supplement, 'is_flash' => $v['is_flash'],
            'is_sponsorise' => $sponso['is_sponsorise'], 'sponsor_formule' => $sponso['formule'],
            'sponsor_tarif' => $sponso['tarif'], 'sponsor_nouvelle' => $sponso['nouvelle'],
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        logErreur('Enregistrement d\'événement impossible', $e, ['etablissement' => $etabId]);

        return ['erreurs' => ['L\'événement n\'a pas pu être enregistré. Réessayez dans un instant.'], 'valeurs' => $v];
    }

    if ($existant === null) {
        fluxToucher($pdo, CANAL_GLOBAL);
    }
    fluxToucher($pdo, canalEvenement($id));

    $message = $existant === null ? 'Événement publié.' : 'Événement mis à jour.';
    if ($supplement > 0) {
        $message .= ' Supplément de ' . eurosSite($supplement) . ' HT ajouté à votre mois.';
    }
    if ($sponso['nouvelle'] && (float) $sponso['tarif'] > 0) {
        $message .= ' Mise en avant de ' . eurosSite((float) $sponso['tarif']) . ' HT ajoutée à votre mois.';
    }
    flashPro($message);
    header('Location: ' . baseUrl('/partenaire/evenements.php'));
    exit;
}

/**
 * Le formulaire, prérempli.
 *
 * @param array<string, mixed> $etab
 * @param array<string, mixed> $capacites
 * @param array<string, mixed> $valeurs
 * @param array<string, mixed>|null $existant
 */
function formulaireEvenementHtml(PDO $pdo, array $etab, array $capacites, array $valeurs, ?array $existant): string
{
    $val     = static fn (string $cle, $defaut = '') => $valeurs[$cle] ?? $defaut;
    $h       = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES);
    $estBde  = ($etab['type'] ?? '') === 'bde';
    $usage   = usagePartenaireMois($pdo, (int) $etab['id'], $capacites);
    $suppl   = $existant === null && $usage['evenements']['restants'] <= 0;
    $flashOk = flashDisponible($pdo, (int) $etab['id'], $capacites, $existant ? (int) $existant['id'] : null) || !empty($val('is_flash'));
    $offerte = $usage['mises_en_avant']['offertes_restantes'] > 0;

    ob_start();
    ?>
    <form method="post" class="grille-tdb" novalidate>
      <?= csrfField() ?>
      <div class="pile">
        <section class="carte formulaire" aria-labelledby="f-infos">
          <h2 class="carte__titre" id="f-infos">L'événement</h2>
          <div class="champ">
            <label for="titre">Titre</label>
            <input type="text" id="titre" name="titre" required maxlength="255" value="<?= $h($val('titre')) ?>" placeholder="Soirée étudiante : DJ et happy hour">
          </div>
          <div class="formulaire__ligne">
            <?php if ($estBde): ?>
            <div class="champ">
              <label for="type">Type de soirée</label>
              <select id="type" name="type" required>
                <?php foreach (['bar' => 'Bar', 'boite' => 'Boîte de nuit', 'resto' => 'Restaurant', 'afterwork' => 'Afterwork'] as $code => $lib): ?>
                  <option value="<?= $code ?>"<?= $val('type') === $code ? ' selected' : '' ?>><?= $lib ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <div class="champ">
              <label for="style_musique">Style de musique</label>
              <select id="style_musique" name="style_musique">
                <option value="">Non précisé</option>
                <?php foreach (stylesMusique() as $code => $lib): ?>
                  <option value="<?= $h($code) ?>"<?= $val('style_musique') === $code ? ' selected' : '' ?>><?= $h($lib) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="formulaire__ligne">
            <div class="champ">
              <label for="date_heure">Début</label>
              <input type="datetime-local" id="date_heure" name="date_heure" required value="<?= $h(valeurDateLocale($val('date_heure') ?: null)) ?>">
            </div>
            <div class="champ">
              <label for="date_fin">Fin <span class="champ__aide">(facultatif)</span></label>
              <input type="datetime-local" id="date_fin" name="date_fin" value="<?= $h(valeurDateLocale($val('date_fin') ?: null)) ?>">
            </div>
          </div>
          <div class="champ">
            <label for="description">Description</label>
            <textarea id="description" name="description" placeholder="L'ambiance, les animations, ce qui est compris avec la réduction…"><?= $h($val('description')) ?></textarea>
          </div>
          <div class="champ">
            <label for="lieu">Lieu</label>
            <input type="text" id="lieu" name="lieu" maxlength="255" value="<?= $h($val('lieu', $etab['adresse'] ?? '')) ?>" placeholder="<?= $h($etab['adresse'] ?? $etab['nom']) ?>">
            <span class="champ__aide">Laissez l'adresse de l'établissement si la soirée a lieu chez vous.</span>
          </div>
        </section>

        <section class="carte formulaire" aria-labelledby="f-offre">
          <h2 class="carte__titre" id="f-offre">Places et avantage étudiant</h2>
          <div class="formulaire__ligne formulaire__ligne--3">
            <div class="champ">
              <label for="quota">Places</label>
              <input type="number" id="quota" name="quota" min="1" max="10000" required value="<?= (int) $val('quota', 50) ?>">
            </div>
            <div class="champ">
              <label for="prix_normal">Prix habituel (€)</label>
              <input type="number" id="prix_normal" name="prix_normal" min="0" step="0.5" value="<?= $h($val('prix_normal', 0)) ?>">
            </div>
            <div class="champ">
              <label for="reduction">Réduction (%)</label>
              <input type="number" id="reduction" name="reduction" min="0" max="100" value="<?= (int) $val('reduction', 0) ?>">
            </div>
          </div>
          <label class="interrupteur">
            <div><strong>Entrée gratuite</strong><span>Affiché « Gratuit » dans le fil des étudiants.</span></div>
            <input type="checkbox" name="is_gratuit" value="1"<?= !empty($val('is_gratuit')) ? ' checked' : '' ?>>
          </label>
        </section>
      </div>

      <aside class="pile">
        <section class="carte formulaire" aria-labelledby="f-visibilite">
          <h2 class="carte__titre" id="f-visibilite">Visibilité</h2>

          <label class="interrupteur">
            <div><strong><?= iconeSite('eclair', 'icon-sm') ?> Offre flash</strong>
              <span><?= $capacites['flash_par_mois'] === null ? 'Illimitées avec votre formule.' : (int) $usage['flash']['restants'] . ' restante(s) ce mois-ci sur ' . (int) $capacites['flash_par_mois'] . '.' ?></span></div>
            <input type="checkbox" name="is_flash" value="1" data-ouvre="bloc-flash"<?= !empty($val('is_flash')) ? ' checked' : '' ?><?= $flashOk ? '' : ' disabled' ?>>
          </label>
          <div class="champ" id="bloc-flash"<?= empty($val('is_flash')) ? ' hidden' : '' ?>>
            <label for="flash_expiry">L'offre flash court jusqu'au</label>
            <input type="datetime-local" id="flash_expiry" name="flash_expiry" value="<?= $h(valeurDateLocale($val('flash_expiry') ?: null)) ?>">
          </div>

          <label class="interrupteur">
            <div><strong><?= iconeSite('etoile', 'icon-sm') ?> Mise en avant</strong>
              <span><?= $offerte ? 'Une mise en avant offerte disponible ce mois-ci.' : 'En tête du fil des étudiants, facturée au mois.' ?></span></div>
            <input type="checkbox" name="is_sponsorise" value="1" data-ouvre="bloc-sponso"<?= !empty($val('is_sponsorise')) ? ' checked' : '' ?>>
          </label>
          <div class="choix choix--liste" id="bloc-sponso" role="radiogroup" aria-label="Formule de mise en avant"<?= empty($val('is_sponsorise')) ? ' hidden' : '' ?>>
            <?php foreach (formulesSponsoring() as $code => $f): ?>
              <label>
                <input type="radio" name="sponsor_formule" value="<?= $h($code) ?>"<?= $val('sponsor_formule') === $code ? ' checked' : '' ?>>
                <?= $h($f['nom']) ?> · <?= $h(eurosSite((float) $f['tarif'])) ?> HT
                <small><?= $h($f['resume']) ?></small>
              </label>
            <?php endforeach; ?>
          </div>
          <?php if ($existant && !empty($existant['is_sponsorise']) && sponsoringActif($existant)): ?>
            <p class="carte__sous">Mise en avant en cours jusqu'au <?= $h(dateFr($existant['sponsor_jusqu_au'], 'D j M')) ?> à <?= date('H\hi', strtotime((string) $existant['sponsor_jusqu_au'])) ?> : enregistrer ne la relance pas.</p>
          <?php endif; ?>
        </section>

        <section class="carte<?= $suppl ? '' : ' carte--accent' ?>">
          <p class="surtitre">Ce mois-ci</p>
          <?php if ($suppl): ?>
            <p style="margin-top:8px;font-weight:600;">Votre quota de <?= (int) $usage['evenements']['quota'] ?> événements est atteint.</p>
            <label class="case" style="margin-top:14px;">
              <input type="checkbox" name="accepte_supplement" value="1"<?= !empty($val('accepte_supplement')) ? ' checked' : '' ?>>
              <span>J'accepte le supplément de <strong><?= $h(eurosSite((float) PRIX_EVENEMENT_SUPPLEMENTAIRE)) ?> HT</strong> pour publier cet événement, ajouté à ma facture du mois.</span>
            </label>
          <?php elseif ($existant === null): ?>
            <p style="margin-top:8px;font-size:18px;font-weight:700;">Inclus dans votre formule</p>
            <p class="carte__sous" style="margin-top:4px;">Il vous restera <?= max(0, (int) $usage['evenements']['restants'] - 1) ?> événement(s) compris ce mois-ci.</p>
          <?php else: ?>
            <p style="margin-top:8px;font-size:18px;font-weight:700;">Modifier ne coûte rien</p>
            <p class="carte__sous" style="margin-top:4px;">Seule une nouvelle offre flash ou une nouvelle mise en avant est décomptée.</p>
          <?php endif; ?>
        </section>

        <div class="formulaire__pied">
          <a class="bouton bouton--clair" href="<?= $h(baseUrl('/partenaire/evenements.php')) ?>">Annuler</a>
          <button type="submit" class="bouton bouton--plein"><?= $existant === null ? 'Publier l\'événement' : 'Enregistrer' ?></button>
        </div>
      </aside>
    </form>
    <script>
    document.querySelectorAll('[data-ouvre]').forEach(function (c) {
      var bloc = document.getElementById(c.getAttribute('data-ouvre'));
      c.addEventListener('change', function () { bloc.hidden = !c.checked; });
    });
    </script>
    <?php
    return (string) ob_get_clean();
}

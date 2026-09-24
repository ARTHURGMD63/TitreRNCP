<?php
/**
 * Centre de notifications de l'étudiant.
 *
 * Ces éléments vivaient dans trois sections du profil (demandes d'abonnement,
 * invitations reçues, activité des amis). Le profil est l'endroit où l'on
 * règle son compte, pas celui où l'on apprend ce qui vient d'arriver : on n'y
 * passe presque jamais, et une invitation qu'on découvre trois jours plus
 * tard ne sert plus à rien. Tout est regroupé derrière la cloche du hub.
 *
 * Aucune table de notifications : chaque élément se déduit de ce qui existe
 * déjà (demandes, invitations, inscriptions, événements publiés). Rien à
 * écrire, donc rien à synchroniser — et une demande annulée disparaît d'elle
 * même, au lieu de laisser une ligne morte dans un journal.
 */

require_once __DIR__ . '/social.php';
require_once __DIR__ . '/uploads.php';
require_once __DIR__ . '/icons.php';

/**
 * Fenêtre au-delà de laquelle une nouvelle n'en est plus une.
 *
 * Vingt-quatre heures : la cloche dit ce qui vient d'arriver, pas ce qui s'est
 * passé ce mois-ci. Au-delà d'une journée, « Léa va au Bec qui Pique » n'est
 * plus une nouvelle mais une ligne d'archive qu'on fait défiler, et qui
 * enterre celles du jour.
 *
 * Ne s'applique qu'aux éléments qui informent. Une demande d'abonnement ou une
 * invitation attend une réponse, et ne se répond nulle part ailleurs : elle
 * reste tant qu'on ne l'a pas traitée, sans quoi une invitation reçue un
 * vendredi soir deviendrait impossible à accepter le dimanche.
 */
const NOTIF_FENETRE_HEURES = 24;

/**
 * Âge d'une notification, en clair.
 *
 * Une date absolue (« 12 sept. ») oblige à calculer de tête si la nouvelle
 * est fraîche. Au-delà d'une semaine le calcul s'inverse : c'est la date qui
 * situe, et « il y a 23 jours » ne dit plus rien.
 */
function depuisQuand(string $ts): string
{
    $secondes = time() - (int) strtotime($ts);

    if ($secondes < 60)     return "à l'instant";
    if ($secondes < 3600)   return 'il y a ' . (int) ($secondes / 60) . ' min';
    if ($secondes < 86400)  return 'il y a ' . (int) ($secondes / 3600) . ' h';
    if ($secondes < 172800) return 'hier';
    if ($secondes < 604800) return 'il y a ' . (int) ($secondes / 86400) . ' j';

    return dateFr($ts, 'j M');
}

/**
 * Les notifications d'un étudiant, de la plus récente à la plus ancienne.
 *
 * Chaque élément porte un `type` qui décide de son rendu :
 *   - `demande`    : quelqu'un demande à te suivre   (à traiter)
 *   - `invitation` : quelqu'un t'invite à une sortie (à traiter)
 *   - `ami`        : un abonnement rejoint une sortie
 *   - `lieu`       : un lieu suivi publie un événement
 *
 * @return array{items: list<array<string,mixed>>, aTraiter: int}
 */
function notificationsEtudiant(PDO $pdo, int $uid, int $limite = 25): array
{
    // Un seul appel : blockedIds interroge la base, et quatre requêtes
    // filtrées séparément l'auraient fait quatre fois.
    $bloques = blockedIds($pdo, $uid);
    $visible = static fn(?int $autre): bool => $autre === null || !in_array($autre, $bloques, true);

    $items = [];

    // ── Demandes d'abonnement en attente ────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT u.id, u.prenom, u.nom, u.ecole, u.promo, u.photo, f.created_at AS ts
        FROM follows_users f
        JOIN users u ON u.id = f.follower_id
        WHERE f.followed_id = ? AND f.statut = 'pending'
        ORDER BY f.created_at DESC
        LIMIT 25
    ");
    $stmt->execute([$uid]);
    foreach ($stmt->fetchAll() as $d) {
        if (!$visible((int) $d['id'])) {
            continue;
        }
        $items[] = [
            'type'    => 'demande',
            'ts'      => $d['ts'],
            'traiter' => true,
            'acteur'  => $d,
        ];
    }

    // ── Invitations reçues ──────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT inv.id, inv.type AS cible_type, inv.target_id, inv.created_at AS ts,
               u.id AS from_id, u.prenom AS from_prenom, u.nom AS from_nom, u.photo AS from_photo,
               CASE WHEN inv.type = 'event' THEN e.titre ELSE s.titre END AS cible_nom
        FROM invitations inv
        JOIN users u ON u.id = inv.from_user_id
        LEFT JOIN evenements e ON inv.type = 'event' AND e.id = inv.target_id
        LEFT JOIN squads s     ON inv.type = 'squad' AND s.id = inv.target_id
        WHERE inv.to_user_id = ? AND inv.statut = 'pending'
        ORDER BY inv.created_at DESC
        LIMIT 25
    ");
    $stmt->execute([$uid]);
    foreach ($stmt->fetchAll() as $inv) {
        if (!$visible((int) $inv['from_id'])) {
            continue;
        }
        $items[] = [
            'type'    => 'invitation',
            'ts'      => $inv['ts'],
            'traiter' => true,
            'invit'   => $inv,
        ];
    }

    // ── Activité des abonnements ────────────────────────────────────────
    // Bornée à la fenêtre : « Léa va au Bec qui Pique » cesse d'être une
    // nouvelle le lendemain, même si la sortie, elle, est à venir.
    $stmt = $pdo->prepare("
        SELECT 'event' AS cible_type, u.id AS acteur_id, u.prenom, u.nom, u.photo,
               e.id AS cible_id, e.titre AS cible_nom, et.nom AS lieu, i.created_at AS ts
        FROM follows_users fu
        JOIN inscriptions i ON i.user_id = fu.followed_id AND i.statut = 'inscrit'
        JOIN evenements e ON e.id = i.evenement_id AND e.date_heure >= NOW()
        JOIN users u ON u.id = fu.followed_id
        JOIN etablissements et ON et.id = e.etablissement_id
        WHERE fu.follower_id = ? AND fu.statut = 'accepted'
          AND i.created_at >= NOW() - INTERVAL " . NOTIF_FENETRE_HEURES . " HOUR

        UNION ALL

        SELECT 'squad' AS cible_type, u.id AS acteur_id, u.prenom, u.nom, u.photo,
               s.id AS cible_id, s.titre AS cible_nom, CONCAT('Session ', s.type) AS lieu, sm.joined_at AS ts
        FROM follows_users fu
        JOIN squad_membres sm ON sm.user_id = fu.followed_id
        JOIN squads s ON s.id = sm.squad_id AND s.date_heure >= NOW()
        JOIN users u ON u.id = fu.followed_id
        WHERE fu.follower_id = ? AND fu.statut = 'accepted'
          AND sm.joined_at >= NOW() - INTERVAL " . NOTIF_FENETRE_HEURES . " HOUR

        ORDER BY ts DESC
        LIMIT 25
    ");
    $stmt->execute([$uid, $uid]);
    foreach ($stmt->fetchAll() as $a) {
        if (!$visible((int) $a['acteur_id'])) {
            continue;
        }
        $items[] = ['type' => 'ami', 'ts' => $a['ts'], 'traiter' => false, 'activite' => $a];
    }

    // ── Nouveautés des lieux suivis ─────────────────────────────────────
    // e.created_at >= fe.created_at : seuls les événements publiés APRÈS
    // l'abonnement comptent. Sans cette borne, suivre un bar déversait tout
    // son catalogue dans la cloche le jour même.
    $stmt = $pdo->prepare("
        SELECT e.id, e.titre, e.date_heure, e.created_at AS ts, et.nom AS lieu
        FROM follows_etablissements fe
        JOIN evenements e ON e.etablissement_id = fe.etablissement_id
        JOIN etablissements et ON et.id = fe.etablissement_id
        WHERE fe.user_id = ?
          AND e.date_heure >= NOW()
          AND e.created_at >= fe.created_at
          AND e.created_at >= NOW() - INTERVAL " . NOTIF_FENETRE_HEURES . " HOUR
        ORDER BY e.created_at DESC
        LIMIT 25
    ");
    $stmt->execute([$uid]);
    foreach ($stmt->fetchAll() as $e) {
        $items[] = ['type' => 'lieu', 'ts' => $e['ts'], 'traiter' => false, 'event' => $e];
    }

    // Ce qui attend une réponse n'est jamais coupé par la limite : sinon la
    // pastille annonce six demandes et le panneau n'en montre que cinq, la
    // sixième étant poussée dehors par l'activité des amis. La limite ne
    // s'applique donc qu'au reste, qui n'appelle aucun geste.
    $aTraiter = array_values(array_filter($items, static fn(array $i): bool => $i['traiter']));
    $leReste  = array_values(array_filter($items, static fn(array $i): bool => !$i['traiter']));
    $items    = array_merge($aTraiter, array_slice($leReste, 0, max(0, $limite - count($aTraiter))));

    // Tout se mélange ensuite dans un seul fil chronologique : séparer
    // « à traiter » et « le reste » obligerait à lire deux listes pour savoir
    // ce qui vient d'arriver. Les boutons suffisent à distinguer l'un de
    // l'autre.
    usort($items, static fn(array $a, array $b): int => strcmp((string) $b['ts'], (string) $a['ts']));

    return ['items' => $items, 'aTraiter' => count($aTraiter)];
}

/**
 * Rendu d'une notification — une seule écriture, partagée par la feuille de
 * la cloche (explore.php) et la page Notifications (notifications.php).
 *
 * Le balisage est celui qui vivait dans explore.php, déplacé tel quel : les
 * boutons Accepter / Refuser gardent leurs classes et leurs data-*, et app.js
 * les branche sur n'importe quelle page.
 *
 * @param array<string,mixed> $n un élément de notificationsEtudiant()['items']
 */
function notificationHtml(array $n): string
{
    ob_start();
?>
          <?php if ($n['type'] === 'demande'): $d = $n['acteur']; ?>
            <div class="notif-item notif-item--demande">
              <a href="<?= baseUrl('/view_profile.php?id=' . (int)$d['id']) ?>" class="notif-item__lien">
                <?= avatarHtml($d['photo'] ?? null, $d['prenom'], 38) ?>
                <span class="notif-item__corps">
                  <span class="notif-item__texte">
                    <strong><?= htmlspecialchars($d['prenom'] . ' ' . mb_substr((string)$d['nom'], 0, 1) . '.') ?></strong>
                    demande à te suivre
                  </span>
                  <span class="notif-item__date"><?= htmlspecialchars(depuisQuand((string)$n['ts'])) ?></span>
                </span>
              </a>
              <div class="notif-item__actions">
                <button class="btn-accept-follow notif-oui" data-user-id="<?= (int)$d['id'] ?>">Accepter</button>
                <button class="btn-decline-follow notif-non" data-user-id="<?= (int)$d['id'] ?>">Refuser</button>
              </div>
            </div>

          <?php elseif ($n['type'] === 'invitation'): $inv = $n['invit']; ?>
            <div class="notif-item carte-invitation">
              <div class="notif-item__lien">
                <?= avatarHtml($inv['from_photo'] ?? null, $inv['from_prenom'], 38, $inv['cible_type'] === 'event' ? 'var(--rouge)' : 'var(--bleu)') ?>
                <span class="notif-item__corps">
                  <span class="notif-item__texte">
                    <strong><?= htmlspecialchars($inv['from_prenom'] . ' ' . mb_substr((string)$inv['from_nom'], 0, 1) . '.') ?></strong>
                    t'invite à <em><?= htmlspecialchars($inv['cible_nom'] ?? 'une sortie') ?></em>
                  </span>
                  <span class="notif-item__date"><?= htmlspecialchars(depuisQuand((string)$n['ts'])) ?></span>
                </span>
              </div>
              <div class="notif-item__actions">
                <button type="button" class="btn-accept-invite notif-oui" data-id="<?= (int)$inv['id'] ?>">Accepter</button>
                <button type="button" class="btn-decline-invite notif-non" data-id="<?= (int)$inv['id'] ?>">Refuser</button>
              </div>
            </div>

          <?php elseif ($n['type'] === 'ami'): $a = $n['activite']; ?>
            <a class="notif-item notif-item__lien" href="<?= $a['cible_type'] === 'event'
                  ? baseUrl('/view_event.php?id=' . (int)$a['cible_id'])
                  : baseUrl('/squads.php') ?>">
              <?= avatarHtml($a['photo'] ?? null, $a['prenom'], 38, $a['cible_type'] === 'event' ? 'var(--rouge)' : 'var(--lime)') ?>
              <span class="notif-item__corps">
                <span class="notif-item__texte">
                  <strong><?= htmlspecialchars($a['prenom']) ?></strong>
                  <?= $a['cible_type'] === 'event' ? 'va à' : 'rejoint' ?>
                  <em><?= htmlspecialchars($a['cible_nom']) ?></em>
                </span>
                <span class="notif-item__date"><?= htmlspecialchars($a['lieu'] . ' · ' . depuisQuand((string)$n['ts'])) ?></span>
              </span>
            </a>

          <?php else: $e = $n['event']; ?>
            <a class="notif-item notif-item__lien" href="<?= baseUrl('/view_event.php?id=' . (int)$e['id']) ?>">
              <span class="notif-item__vignette"><?= icon('calendrier', 'icon-sm') ?></span>
              <span class="notif-item__corps">
                <span class="notif-item__texte">
                  Nouveau chez <strong><?= htmlspecialchars($e['lieu']) ?></strong> :
                  <em><?= htmlspecialchars($e['titre']) ?></em>
                </span>
                <span class="notif-item__date"><?= htmlspecialchars(dateFr($e['date_heure'], 'j M') . ' · ' . depuisQuand((string)$n['ts'])) ?></span>
              </span>
            </a>
          <?php endif; ?>
<?php
    return (string) ob_get_clean();
}

/**
 * Instant où l'étudiant a tout lu, ou null s'il ne l'a jamais fait.
 *
 * Null aussi tant que la migration v17 n'est pas posée : la page s'affiche
 * alors comme avant, tout en « nouveau », plutôt que de tomber en erreur.
 */
function notificationsLuesLe(PDO $pdo, int $uid): ?string
{
    try {
        $stmt = $pdo->prepare("SELECT lues_le FROM notifications_lues WHERE user_id = ?");
        $stmt->execute([$uid]);
        $v = $stmt->fetchColumn();
        return $v === false || $v === null ? null : (string) $v;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Lecture des notifications : retient l'instant présent. Faux si la migration v17 manque.
 */
function marquerNotificationsLues(PDO $pdo, int $uid): bool
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications_lues (user_id, lues_le) VALUES (?, NOW())
            ON DUPLICATE KEY UPDATE lues_le = NOW()");
        return $stmt->execute([$uid]);
    } catch (PDOException $e) {
        return false;
    }
}

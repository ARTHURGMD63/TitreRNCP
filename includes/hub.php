<?php
/**
 * Le hub étudiant — la couche données.
 *
 * Pourquoi ce fichier existe. explore.php faisait 1 051 lignes : quatre cents
 * de construction de requêtes, six cents de gabarit, et rien entre les deux.
 * C'était le fichier le plus complexe de l'application — dix requêtes, deux
 * classements par affinité, deux paginations — et le seul qu'aucun test ne
 * pouvait atteindre, puisque le lire exécutait aussi son HTML.
 *
 * Le découpage ne déplace pas le problème, il le rend vérifiable :
 *
 *   • hubCriteres() traduit $_GET en intentions validées. C'est du calcul
 *     pur, sans base ni session : c'est là que se testent les cas tordus
 *     — page négative, style de musique inventé, filtre « comme moi » sans
 *     aucun goût déclaré (voir tests/Unit/HubTest.php).
 *   • hubAnnuaire() et hubEvenements() rendent chacune un tableau complet,
 *     que le gabarit se contente de parcourir. Aucune requête ne part plus
 *     depuis le gabarit.
 *
 * Ce qui n'a PAS changé : les requêtes elles-mêmes, et ce qu'elles coûtent.
 * Le travail de montée en charge — classement et découpage côté base,
 * LIMIT n+1 à la place de COUNT(*), agrégats mis en cache — est repris tel
 * quel, commentaires compris. Un découpage qui se paierait en performance
 * n'aurait aucun intérêt.
 */

require_once __DIR__ . '/interets.php';
require_once __DIR__ . '/social.php';
require_once __DIR__ . '/musique.php';
require_once __DIR__ . '/agregats.php';

/**
 * Valeur réservée du filtre d'intérêt : « les gens qui aiment ce que j'aime »,
 * par opposition à « les gens qui aiment X ». Aucun intérêt du catalogue ne
 * porte ce nom.
 */
const FILTRE_MES_INTERETS = '__moi__';

/** Profils affichés par palier de « voir plus ». */
const HUB_PROFILS_PAR_PAGE = 24;

/** Soirées affichées par palier de « voir plus ». */
const HUB_EVENEMENTS_PAR_PAGE = 24;

/**
 * Traduit les paramètres d'URL en critères validés.
 *
 * Tout ce qui vient de l'extérieur est ramené ici à une valeur sûre, une
 * seule fois, plutôt que d'être revérifié — ou oublié — à chaque usage.
 * Un style de musique inconnu (lien périmé, URL bricolée) ne filtre rien
 * plutôt que de rendre une page vide qu'on prendrait pour « aucune soirée
 * ce soir ».
 *
 * @param array<string,mixed> $get typiquement $_GET
 * @return array{vue:string, type:string, musique:string, q:string,
 *               ecole:string, interet:string, comme_moi:bool,
 *               page_profils:int, page_evenements:int}
 */
function hubCriteres(array $get): array
{
    $musique = is_string($get['musique'] ?? null) ? $get['musique'] : '';
    $interet = trim(is_string($get['interest'] ?? null) ? $get['interest'] : '');

    // Deux vues seulement : une valeur inconnue retombe sur les soirées,
    // qui est la page d'accueil de l'application.
    $vue = ($get['view'] ?? 'events') === 'people' ? 'people' : 'events';

    return [
        'vue'             => $vue,
        'type'            => is_string($get['type'] ?? null) ? $get['type'] : 'all',
        'musique'         => styleMusiqueValide($musique) ? $musique : '',
        'q'               => trim(is_string($get['q'] ?? null) ? $get['q'] : ''),
        'ecole'           => trim(is_string($get['ecole'] ?? null) ? $get['ecole'] : ''),
        // `interet` garde la valeur réservée telle quelle plutôt que de la
        // vider quand `comme_moi` est vrai. Ce n'est pas une redondance :
        // « un filtre d'intérêt est actif » et « lequel » sont deux questions
        // différentes, et c'est la première qui décide de masquer les
        // suggestions d'abonnement. Les vider ici les faisait réapparaître
        // sur « comme moi », où elles n'ont rien à faire : la liste entière
        // est déjà filtrée sur les goûts communs.
        'interet'         => $interet,
        'comme_moi'       => $interet === FILTRE_MES_INTERETS,
        // max(1, …) : « ?p=-3 » ou « ?p=abc » ne doit pas produire un LIMIT
        // négatif, qui est une erreur SQL et non une page vide.
        'page_profils'    => max(1, (int) ($get['p'] ?? 1)),
        'page_evenements' => max(1, (int) ($get['pe'] ?? 1)),
    ];
}

/**
 * Les personnes que l'utilisateur suit — alimente la fenêtre d'invitation.
 *
 * @return list<array<string,mixed>>
 */
function hubAbonnements(PDO $pdo, int $uid): array
{
    $stmt = $pdo->prepare(
        "SELECT u.id, u.prenom, u.nom, u.photo
           FROM follows_users f
           JOIN users u ON u.id = f.followed_id
          WHERE f.follower_id = ? AND f.statut = 'accepted' AND u.type = 'etudiant'"
    );
    $stmt->execute([$uid]);

    return $stmt->fetchAll();
}

/**
 * Les établissements suivis, par identifiant.
 *
 * @return list<int>
 */
function hubEtablissementsSuivis(PDO $pdo, int $uid): array
{
    $stmt = $pdo->prepare("SELECT etablissement_id FROM follows_etablissements WHERE user_id = ?");
    $stmt->execute([$uid]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Qui, parmi les personnes suivies, va à quelle soirée.
 *
 * Une seule requête groupée pour tout le fil : la même information par carte
 * aurait coûté une requête par événement affiché.
 *
 * @return array<int,array{prenoms:list<string>,nb:int}>
 */
function hubAmisParEvenement(PDO $pdo, int $uid): array
{
    $stmt = $pdo->prepare("
        SELECT i.evenement_id,
               GROUP_CONCAT(u.prenom ORDER BY i.created_at SEPARATOR ',') AS prenoms,
               COUNT(*) AS nb
          FROM follows_users fu
          JOIN inscriptions i ON i.user_id = fu.followed_id AND i.statut = 'inscrit'
          JOIN users u ON u.id = fu.followed_id
         WHERE fu.follower_id = ? AND fu.statut = 'accepted'
         GROUP BY i.evenement_id
    ");
    $stmt->execute([$uid]);

    $par = [];
    foreach ($stmt->fetchAll() as $ligne) {
        $par[(int) $ligne['evenement_id']] = [
            'prenoms' => explode(',', (string) $ligne['prenoms']),
            'nb'      => (int) $ligne['nb'],
        ];
    }

    return $par;
}

/**
 * L'annuaire étudiant : suggestions, liste, et de quoi remplir les filtres.
 *
 * @param array{id:?int,ecole:string} $utilisateur l'identité du visiteur
 * @param array<string,mixed>         $criteres    sortie de hubCriteres()
 * @return array{profils:list<array<string,mixed>>, suggestions:list<array<string,mixed>>,
 *               mes_interets:list<string>, ecoles:list<string>, catalogue:list<string>,
 *               reste:bool, interets_manquants:bool, comme_moi:bool, interet:string}
 */
function hubAnnuaire(PDO $pdo, array $utilisateur, array $criteres): array
{
    $uid            = (int) $utilisateur['id'];
    $q              = (string) $criteres['q'];
    $filterEcole    = (string) $criteres['ecole'];
    $filterInterest = (string) $criteres['interet'];
    $filtreCommeMoi = (bool) $criteres['comme_moi'];

    $stmt = $pdo->prepare("SELECT interests FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $me          = $stmt->fetch();
    $myInterests = interetsDepuisTexte($me['interests'] ?? null);

    // Filtrer sur ses propres goûts quand on n'en a déclaré aucun ne rendrait
    // aucun profil, sans dire pourquoi. On retire le filtre et on l'explique.
    $interetsManquants = false;
    if ($filtreCommeMoi && !$myInterests) {
        $filtreCommeMoi    = false;
        $filterInterest    = '';
        $interetsManquants = true;
    }

    // La liste des ecoles alimente un menu deroulant d'une vingtaine
    // d'entrees. Elle etait recalculee par un DISTINCT sur toute la table a
    // chaque affichage ; elle vient maintenant d'un agregat mis en cache.
    $allEcoles = ecolesRepresentees($pdo);

    // Le catalogue est la source des intérêts, et non la colonne de chaque
    // compte : lire les goûts de tous les inscrits pour composer un menu de
    // vingt-quatre entrées connues d'avance coûtait un balayage complet de la
    // table à chaque affichage de la page.
    $allInterests = interetsDisponibles();

    // ── Annuaire : score, classement et tranche côté base ─────────────
    // La page chargeait tous les étudiants en mémoire, les triait en PHP puis
    // n'en gardait que vingt-quatre. À mille comptes, c'est mille lignes lues
    // pour vingt-quatre affichées, et la mémoire grandit avec les inscriptions.
    // MySQL calcule désormais le score, classe et découpe : il ne renvoie que
    // ce qui s'affiche.

    /*
     * Score d'affinité : un comptage sur index, et non des fonctions de
     * chaîne appliquées à tout l'annuaire.
     *
     * L'écriture précédente — un FIND_IN_SET par intérêt, sur la colonne
     * texte `users.interests` — obligeait MySQL à lire les cinq mille
     * comptes et à exécuter ces fonctions sur chacun, avant de trier, pour
     * n'en afficher que vingt-quatre. Aucun index ne peut servir une
     * recherche à l'intérieur d'une chaîne : c'était structurel, pas un
     * réglage à trouver.
     *
     * `user_interets` (migration v15) range la même information en lignes.
     * La dérivée ci-dessous ne remonte que les profils partageant au moins
     * un goût — quelques centaines plutôt que cinq mille — et le fait en
     * parcourant l'index k_interet, sans jamais toucher à la table.
     */
    $jointureScore = '';
    $parScore      = [];
    if ($myInterests) {
        $trousI        = implode(',', array_fill(0, count($myInterests), '?'));
        $jointureScore = " LEFT JOIN (
                SELECT ui.user_id AS sc_user, COUNT(*) AS sc_nb
                  FROM user_interets ui
                 WHERE ui.interet IN ($trousI)
                 GROUP BY ui.user_id
            ) sc ON sc.sc_user = u.id";
        $parScore = $myInterests;
    }
    // Sans goût déclaré, tout le monde est à égalité : inutile de joindre.
    $sqlScore = $myInterests ? 'COALESCE(sc.sc_nb, 0)' : '0';

    /*
     * Squads partagées : une agrégation jointe, et non une sous-requête
     * corrélée.
     *
     * Écrite en sous-requête — « (SELECT COUNT(*) … WHERE sm2.user_id = u.id) » —
     * l'expression était réévaluée pour CHAQUE profil balayé, et le classement
     * par affinité oblige à balayer tout l'annuaire avant de pouvoir couper à
     * vingt-quatre. À cinq mille inscrits, c'étaient cinq mille jointures pour
     * en afficher vingt-quatre. En dérivée, mes squads sont agrégées une fois,
     * puis rattachées.
     */
    $jointureSquads = " LEFT JOIN (
            SELECT sm2.user_id AS sq_user, COUNT(*) AS sq_nb
              FROM squad_membres sm1
              JOIN squad_membres sm2 ON sm2.squad_id = sm1.squad_id
             WHERE sm1.user_id = ?
             GROUP BY sm2.user_id
        ) sq ON sq.sq_user = u.id";

    // Ce qui suffit à classer : l'identifiant et les deux critères. Le reste du
    // profil — nom, photo, intérêts — n'a aucune raison de traverser le tampon
    // de tri de MySQL pour être jeté aussitôt.
    $colonnesTri = "u.id, $sqlScore AS score, COALESCE(sq.sq_nb, 0) AS shared_squads";

    // L'ordre des marqueurs suit l'ordre du texte SQL : le SELECT, puis la
    // jointure dérivée du FROM, puis le WHERE.
    $parTri  = [];
    $parFrom = array_merge($parScore, [$uid]);

    $ou  = " FROM users u $jointureScore $jointureSquads WHERE u.type = 'etudiant' AND u.id <> ?";
    $par = [$uid];

    // Les personnes bloquees (dans un sens ou dans l'autre) disparaissent de l'annuaire.
    [$sqlBlock, $paramsBlock] = blockedFilterSql($pdo, $uid, 'u.id');
    $ou .= $sqlBlock;
    $par = array_merge($par, $paramsBlock);

    if ($q) {
        $ou .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.ecole LIKE ? OR u.interests LIKE ?)";
        $terme = '%' . addcslashes($q, '%_') . '%';
        $par[] = $terme; $par[] = $terme; $par[] = $terme; $par[] = $terme;
    }
    // On vient ici pour rencontrer du monde, pas pour relire la liste de ceux
    // qu'on suit déjà : ces comptes sortent de l'annuaire et se retrouvent
    // depuis le profil, en cliquant sur le compteur d'abonnements. La
    // recherche par nom, elle, les retrouve — taper le prénom d'un ami pour
    // n'obtenir aucun résultat se lirait comme une panne. Les demandes en
    // attente restent aussi visibles, pour pouvoir les annuler.
    if (!$q) {
        $ou .= " AND NOT EXISTS (SELECT 1 FROM follows_users fdeja
                                 WHERE fdeja.follower_id = ? AND fdeja.followed_id = u.id
                                   AND fdeja.statut = 'accepted')";
        $par[] = $uid;
    }
    if ($filterEcole) {
        $ou .= " AND u.ecole = ?";
        $par[] = $filterEcole;
    }
    if ($filtreCommeMoi) {
        // « Les gens qui aiment ce que j'aime » : une existence dans la
        // table indexée, au lieu d'une fonction de chaîne par profil.
        $trousM = implode(',', array_fill(0, count($myInterests), '?'));
        $ou .= " AND EXISTS (SELECT 1 FROM user_interets uim
                              WHERE uim.user_id = u.id AND uim.interet IN ($trousM))";
        $par = array_merge($par, $myInterests);
    } elseif ($filterInterest) {
        $ou .= " AND EXISTS (SELECT 1 FROM user_interets uif
                              WHERE uif.user_id = u.id AND uif.interet = ?)";
        $par[] = $filterInterest;
    }

    // Le classement par affinité vaut aussi pour « comme moi » : c'est même là
    // qu'il compte le plus, puisque tous les profils retenus partagent au moins
    // un intérêt et qu'on veut voir d'abord ceux qui en partagent le plus.
    // Les inscrits récents départagent : sans ce dernier critère, les nouveaux
    // comptes — aucun intérêt renseigné — restaient bloqués en fin de liste.
    $classement = (!$q && !$filterEcole && (!$filterInterest || $filtreCommeMoi))
        ? ' ORDER BY score DESC, shared_squads DESC, u.created_at DESC, u.id DESC'
        : ' ORDER BY u.created_at DESC, u.id DESC';

    // ── Suggestions d'abonnement ───────────────────────────────
    // Quatre profils à suivre, choisis sur les goûts : les intérêts communs
    // pèsent le plus, une squad partagée ensuite, l'école en dernier recours.
    // Sans aucun point commun, pas de suggestion : une vignette « à suivre »
    // qui ne repose sur rien n'apprend rien de plus que la liste en dessous.
    // Les profils retenus en sont retirés pour ne pas y figurer deux fois.
    $idsSuggeres = [];
    if (!$q && !$filterEcole && !$filterInterest) {
        $monEcole    = (string) ($utilisateur['ecole'] ?? '');
        $sqlAffinite = "($sqlScore) * 3 + (COALESCE(sq.sq_nb, 0)) * 2 + (u.ecole = ? AND ? <> '')";

        $sqlSug = "SELECT u.id, $sqlAffinite AS affinite $ou
                   AND NOT EXISTS (SELECT 1 FROM follows_users flien
                                   WHERE flien.follower_id = ? AND flien.followed_id = u.id)
                   HAVING affinite > 0
                   ORDER BY affinite DESC, u.created_at DESC
                   LIMIT 4";
        $stmtSug = $pdo->prepare($sqlSug);
        $stmtSug->execute(array_merge(
            [$monEcole, $monEcole],
            $parFrom,
            $par, [$uid]
        ));
        $idsSuggeres = array_map('intval', $stmtSug->fetchAll(PDO::FETCH_COLUMN));
    }

    if ($idsSuggeres) {
        $trous = implode(',', array_fill(0, count($idsSuggeres), '?'));
        $ou   .= " AND u.id NOT IN ($trous)";
        $par   = array_merge($par, $idsSuggeres);
    }

    // « Voir plus » rallonge la page au lieu de la remplacer : on redemande
    // depuis le début, vingt-quatre profils de plus à chaque fois.
    $limite = HUB_PROFILS_PAR_PAGE * (int) $criteres['page_profils'];

    /*
     * Une ligne de plus que demandé, à la place du COUNT(*).
     *
     * Le total ne servait qu'à décider d'afficher ou non un bouton, et le
     * calculer imposait un troisième balayage complet de l'annuaire, aussi
     * cher que celui qui produit la liste. Savoir s'il reste au moins un
     * profil suffit, et se lit dans la même requête.
     */
    $stmtP = $pdo->prepare("SELECT $colonnesTri $ou $classement LIMIT " . ($limite + 1));
    $stmtP->execute(array_merge($parTri, $parFrom, $par));
    $idsListe = array_map('intval', $stmtP->fetchAll(PDO::FETCH_COLUMN));

    $reste    = count($idsListe) > $limite;
    $idsListe = array_slice($idsListe, 0, $limite);

    /*
     * Le détail des profils retenus, suggestions et liste confondues : une
     * seule requête pour les deux, puis on répartit en PHP. C'est ici, et ici
     * seulement, qu'on paie le statut d'abonnement — pour vingt-huit profils
     * au lieu de cinq mille.
     */
    $detail  = [];
    $tousIds = array_values(array_unique(array_merge($idsSuggeres, $idsListe)));
    if ($tousIds) {
        $trousD = implode(',', array_fill(0, count($tousIds), '?'));
        $stmtD  = $pdo->prepare(
            "SELECT u.id, u.nom, u.prenom, u.ecole, u.promo, u.interests, u.created_at, u.photo,
                    $sqlScore AS score,
                    COALESCE(sq.sq_nb, 0) AS shared_squads,
                    (SELECT fu.statut FROM follows_users fu
                      WHERE fu.follower_id = ? AND fu.followed_id = u.id) AS follow_statut
               FROM users u $jointureScore $jointureSquads
              WHERE u.id IN ($trousD)"
        );
        $stmtD->execute(array_merge([$uid], $parFrom, $tousIds));
        foreach ($stmtD->fetchAll() as $ligne) {
            $detail[(int) $ligne['id']] = $ligne;
        }
    }

    // On rejoue l'ordre établi par les requêtes de classement : un IN() n'en
    // garantit aucun, et l'annuaire se retrouverait trié par identifiant.
    $reprendre = static fn(array $ids): array => array_values(array_filter(
        array_map(static fn(int $id): ?array => $detail[$id] ?? null, $ids)
    ));

    // Les intérêts communs s'affichent sur chaque rangée : ils se recoupent en
    // PHP, mais seulement pour les profils rendus, pas pour toute la table.
    $enrichir = static function (array $profils) use ($myInterests): array {
        // Un map plutôt qu'un foreach par référence : la boucle par référence
        // laissait $s pointer sur le dernier profil, que la boucle d'affichage
        // écrasait ensuite — le dernier de la liste se dédoublait. Le unset()
        // qui corrigeait cela n'est plus nécessaire, l'erreur non plus.
        return array_map(static function (array $p) use ($myInterests): array {
            $p['common_interests'] = array_intersect(
                $myInterests,
                interetsDepuisTexte($p['interests'] ?? null)
            );
            $p['score']         = (int) ($p['score'] ?? 0);
            $p['shared_squads'] = (int) ($p['shared_squads'] ?? 0);

            return $p;
        }, $profils);
    };

    return [
        'profils'            => $enrichir($reprendre($idsListe)),
        'suggestions'        => $enrichir($reprendre($idsSuggeres)),
        'mes_interets'       => $myInterests,
        'ecoles'             => $allEcoles,
        'catalogue'          => $allInterests,
        'reste'              => $reste,
        // Renvoyés parce que la branche « aucun goût déclaré » les a corrigés :
        // le gabarit doit afficher l'état réellement appliqué, pas celui
        // demandé dans l'URL.
        'interets_manquants' => $interetsManquants,
        'comme_moi'          => $filtreCommeMoi,
        'interet'            => $filterInterest,
    ];
}

/**
 * Le fil des soirées à venir.
 *
 * @param array<string,mixed>                       $criteres      sortie de hubCriteres()
 * @param list<int>                                 $etabsSuivis   hubEtablissementsSuivis()
 * @param array<int,array{prenoms:list<string>,nb:int}> $amisParEvent hubAmisParEvenement()
 * @return array{evenements:list<array<string,mixed>>, reste:bool}
 */
function hubEvenements(PDO $pdo, int $uid, array $criteres, array $etabsSuivis, array $amisParEvent): array
{
    $filter  = (string) $criteres['type'];
    $musique = (string) $criteres['musique'];

    /*
     * Le fil des soirées se construit en deux temps, et ce n'est pas un
     * détour : c'est ce qui borne son coût.
     *
     * Avant, une seule requête sélectionnait TOUS les événements à venir,
     * sans limite, en évaluant quatre sous-requêtes corrélées par ligne —
     * dont deux qui rejoignaient `avis` à `evenements` pour recalculer la
     * note d'un bar autant de fois qu'il avait de soirées au programme. Vingt
     * cartes à l'écran, mais le travail était fait pour le catalogue entier.
     *
     * Désormais :
     *   1. une requête ne ramène que les identifiants de la tranche affichée,
     *      sans aucune sous-requête — elle lit un index et trie des entiers ;
     *   2. une seconde va chercher le détail de ces identifiants-là, et d'eux
     *      seuls.
     *
     * Les deux agrégats qui restaient — note de l'établissement, inscriptions
     * de l'utilisateur — ont quitté le SQL : le premier vient d'un cache
     * partagé, le second d'une requête unique recoupée en PHP.
     */
    $sponsoActif = "(e.is_sponsorise = 1
                     AND (e.sponsor_jusqu_au IS NULL OR e.sponsor_jusqu_au > NOW()))";

    $ouE     = " FROM evenements e
                 JOIN etablissements et ON et.id = e.etablissement_id
                 WHERE e.date_heure >= NOW()";
    $paramsE = [];

    if ($filter === 'pour-moi') {
        $friendEventIds     = array_keys($amisParEvent);
        $etabPlaceholders   = $etabsSuivis ? implode(',', array_fill(0, count($etabsSuivis), '?')) : '0';
        $friendPlaceholders = $friendEventIds ? implode(',', array_fill(0, count($friendEventIds), '?')) : '0';
        $ouE .= " AND (et.id IN ($etabPlaceholders) OR e.id IN ($friendPlaceholders))";
        $paramsE = array_merge($paramsE, $etabsSuivis, $friendEventIds);
    } elseif ($filter !== 'all') {
        $ouE .= " AND e.type = ?";
        $paramsE[] = $filter;
    }

    if ($musique !== '') {
        $ouE .= " AND e.style_musique = ?";
        $paramsE[] = $musique;
    }

    // Le sponsoring acheté passe devant le reste du fil — c'est ce que le
    // partenaire paie. Il ne s'en cache pas pour autant : chaque carte
    // concernée porte le badge « Sponsorisé », et la mise en avant expire.
    // e.id départage : sans dernier critère stable, deux soirées à la même
    // heure peuvent changer de place d'un chargement à l'autre, et la
    // pagination sauter ou répéter une carte.
    $classementE = " ORDER BY $sponsoActif DESC, e.is_flash DESC, e.date_heure ASC, e.id ASC";

    // Même mécanique que l'annuaire : « voir plus » rallonge la page.
    $limiteE = HUB_EVENEMENTS_PAR_PAGE * (int) $criteres['page_evenements'];

    // LIMIT + 1 : on demande une ligne de plus que ce qu'on affiche. Sa
    // présence dit qu'il reste quelque chose après, ce qui évite le COUNT(*)
    // complet qu'il aurait fallu sinon — un balayage entier pour afficher ou
    // non un bouton.
    $stmtIds = $pdo->prepare("SELECT e.id $ouE $classementE LIMIT " . ($limiteE + 1));
    $stmtIds->execute($paramsE);
    $idsE = array_map('intval', $stmtIds->fetchAll(PDO::FETCH_COLUMN));

    $reste = count($idsE) > $limiteE;
    $idsE  = array_slice($idsE, 0, $limiteE);

    if (!$idsE) {
        return ['evenements' => [], 'reste' => false];
    }

    $trousE = implode(',', array_fill(0, count($idsE), '?'));
    // FIELD() rejoue l'ordre établi à l'étape 1 : un IN() ne garantit
    // aucun ordre, et le sponsoring payé se retrouverait au hasard.
    $stmtE = $pdo->prepare(
        "SELECT e.*, et.id AS etab_id, et.nom AS etablissement_nom, et.type AS etab_type, et.ville,
                $sponsoActif AS sponso_actif,
                (SELECT COUNT(*) FROM inscriptions i
                  WHERE i.evenement_id = e.id AND i.statut <> 'annule') AS nb_inscrits
           FROM evenements e
           JOIN etablissements et ON et.id = e.etablissement_id
          WHERE e.id IN ($trousE)
          ORDER BY FIELD(e.id, $trousE)"
    );
    $stmtE->execute(array_merge($idsE, $idsE));
    $evenements = $stmtE->fetchAll();

    // Mes inscriptions parmi les soirées affichées : une requête pour
    // toute la page, là où il y avait une sous-requête par carte.
    $stmtMoi = $pdo->prepare(
        "SELECT evenement_id FROM inscriptions
          WHERE user_id = ? AND statut <> 'annule' AND evenement_id IN ($trousE)"
    );
    $stmtMoi->execute(array_merge([$uid], $idsE));
    $mesInscriptions = array_flip(array_map('intval', $stmtMoi->fetchAll(PDO::FETCH_COLUMN)));

    $notesEtab = notesEtablissements($pdo);

    // array_map plutôt qu'un foreach par référence : voir la note de
    // hubAnnuaire(), c'est la même erreur de dernière carte dédoublée.
    $evenements = array_map(
        static function (array $ev) use ($notesEtab, $mesInscriptions): array {
            $note = $notesEtab[(int) $ev['etablissement_id']] ?? null;
            $ev['etab_note']    = $note['note'] ?? null;
            $ev['etab_nb_avis'] = $note['nb']   ?? 0;
            $ev['deja_inscrit'] = isset($mesInscriptions[(int) $ev['id']]) ? 1 : 0;

            return $ev;
        },
        $evenements
    );

    return ['evenements' => $evenements, 'reste' => $reste];
}

/**
 * Construit le lien d'un filtre en conservant l'autre dimension.
 *
 * Les pilules écrivaient leur URL en dur : choisir un style effaçait le type,
 * et inversement. On ne peut pas demander « les boîtes techno » en deux clics
 * si le second annule le premier.
 *
 * @param array<string,string> $change ce que ce lien modifie
 */
function hubLienFiltre(string $type, string $musique, array $change): string
{
    $params = array_merge(
        ['view' => 'events', 'type' => $type, 'musique' => $musique],
        $change
    );
    $params = array_filter($params, static fn($v) => $v !== '' && $v !== null);

    return '?' . htmlspecialchars(http_build_query($params), ENT_QUOTES);
}

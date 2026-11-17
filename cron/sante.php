<?php
/**
 * Surveillance : prévient par e-mail quand le service ne répond plus.
 *
 * À lancer toutes les cinq minutes, en ligne de commande :
 *     php cron/sante.php
 *
 * Options :
 *     --simulation   affiche ce qui serait envoyé, sans rien envoyer ni écrire
 *
 * Ce que le script contrôle :
 *   - la page de santé (sante.php), appelée par HTTP comme le ferait un
 *     visiteur : elle traverse nginx, PHP-FPM et la base, donc la panne de
 *     n'importe laquelle se voit ;
 *   - la fraîcheur de la dernière sauvegarde de la base.
 *
 * Un seul e-mail par panne (« ça ne va plus »), un seul quand tout revient
 * (« c'est rétabli ») : pas une alerte toutes les cinq minutes. L'état est
 * gardé dans un petit fichier du dossier temporaire.
 *
 * Destinataire : ALERTE_EMAIL (ou 'alerte_email' dans config.local.php),
 * contact@outsee.fr par défaut.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/mail.php';
require_once __DIR__ . '/../includes/sante.php';

$simulation = in_array('--simulation', $argv ?? [], true);
$problemes  = [];

// 1. La page de santé, de bout en bout.
$url = urlPublique('/sante.php');
$ch  = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_FOLLOWLOCATION => false,
]);
$corps = curl_exec($ch);
$code  = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$erreurCurl = curl_error($ch);
curl_close($ch);

$reponse = is_string($corps) ? json_decode($corps, true) : null;
if ($code !== 200 || !is_array($reponse) || empty($reponse['ok'])) {
    $detail = $code === 0 ? "aucune réponse ($erreurCurl)" : "code HTTP $code";
    if (is_array($reponse)) {
        $faux = array_keys(array_filter($reponse, static fn ($v): bool => $v === false));
        $detail .= $faux ? ' — en défaut : ' . implode(', ', $faux) : '';
    }
    $problemes[] = "Le site ne répond pas correctement ($url : $detail).";
}

// 2. La sauvegarde de la nuit.
if (!santeSauvegarde()) {
    $problemes[] = 'La dernière sauvegarde de la base date de plus de 36 heures (ou n\'existe pas).';
}

// Une alerte à la panne, une au rétablissement.
$fichierEtat = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'outsee_sante.etat';
$enPanne     = is_file($fichierEtat);
$destinataire = reglage('ALERTE_EMAIL', 'alerte_email', 'contact@outsee.fr');

if ($problemes && !$enPanne) {
    $sujet = 'Outsee — ALERTE : le service a un problème';
    $texte = "Détecté le " . date('d/m/Y à H:i') . " :\n\n- " . implode("\n- ", $problemes)
           . "\n\nUn seul message est envoyé par panne ; un autre suivra quand tout sera rétabli.\n";
    if ($simulation) {
        echo "[simulation] $sujet\n$texte";
    } else {
        envoyerEmail($destinataire, $sujet, $texte);
        file_put_contents($fichierEtat, (string) time());
    }
    exit(1);
}

if (!$problemes && $enPanne) {
    $sujet = 'Outsee — Rétabli : le service répond de nouveau';
    $texte = "Tout est revenu à la normale le " . date('d/m/Y à H:i') . ".\n";
    if ($simulation) {
        echo "[simulation] $sujet\n$texte";
    } else {
        envoyerEmail($destinataire, $sujet, $texte);
        @unlink($fichierEtat);
    }
}

if ($problemes) {
    echo implode("\n", $problemes), "\n";
    exit(1);
}

echo "OK\n";

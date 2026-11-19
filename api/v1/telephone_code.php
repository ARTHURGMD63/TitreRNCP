<?php
/**
 * POST /api/v1/telephone_code.php — envoyer le code SMS de l'inscription.
 *
 * Sans compte : on vérifie le numéro avant de créer le compte. Corps JSON :
 * { telephone: "06 12 34 56 78" }.
 *
 * Réponses :
 *   200 { verification: true, telephone, code_dev? } : un code est parti
 *       (code_dev seulement en développement) ;
 *   200 { verification: false, telephone } : l'envoi de SMS n'est pas
 *       configuré, l'inscription se fera sans code ;
 *   409 telephone_pris, 422 telephone, 429 code_recent / trop_de_tentatives,
 *   502 envoi.
 *
 * Plafonds : cinq demandes par heure et par adresse IP, trois par heure et
 * par numéro. Chaque SMS coûte : sans eux, un script viderait le crédit.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/telephone.php';

apiExigerMethode('POST');

$corps     = apiCorps();
$telephone = telephoneNormaliser(is_scalar($corps['telephone'] ?? null) ? (string) $corps['telephone'] : '');

if ($telephone === null) {
    apiErreur('Saisis un numéro de mobile français (06 ou 07).', 422, 'telephone');
}
if (telephonePris($pdo, $telephone)) {
    apiErreur('Ce numéro est déjà utilisé par un autre compte.', 409, 'telephone_pris');
}

if (!telephoneVerificationRequise()) {
    apiReponse(['success' => true, 'verification' => false, 'telephone' => $telephone]);
}

$ip = ipClient();
if (limiteAtteinte($pdo, 'sms', $ip, 5, 3600, $telephone, 3)) {
    apiErreur('Trop de codes demandés. Réessaie dans une heure.', 429, 'trop_de_tentatives');
}

$envoi = telephoneEnvoyerCode($pdo, $telephone);
if ($envoi['statut'] === 'recent') {
    apiErreur("Un code vient d'être envoyé. Patiente une minute avant d'en demander un autre.", 429, 'code_recent');
}
enregistrerTentative($pdo, 'sms', $ip, $telephone);
if ($envoi['statut'] === 'echec') {
    apiErreur("Le SMS n'a pas pu être envoyé. Vérifie le numéro et réessaie.", 502, 'envoi');
}

apiReponse(['success' => true, 'verification' => true, 'telephone' => $telephone]
    + (isset($envoi['code_dev']) ? ['code_dev' => $envoi['code_dev']] : []));

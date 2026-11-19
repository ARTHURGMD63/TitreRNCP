<?php
/**
 * Envoi de SMS par l'API d'OVHcloud (offre SMS).
 *
 * Seul usage aujourd'hui : le code qui vérifie le numéro de téléphone à
 * l'inscription (includes/telephone.php). Aucune dépendance : une requête
 * signée vers https://eu.api.ovh.com/1.0/sms/{service}/jobs.
 *
 * Réglages (variables d'environnement, ou clés de config.local.php) :
 *
 *   OVH_SMS_APP_KEY      ovh_sms_app_key       clé d'application
 *   OVH_SMS_APP_SECRET   ovh_sms_app_secret    secret d'application
 *   OVH_SMS_CONSUMER_KEY ovh_sms_consumer_key  clé client (droit POST /sms/*)
 *   OVH_SMS_SERVICE      ovh_sms_service       compte SMS, « sms-ab12345-1 »
 *   OVH_SMS_EXPEDITEUR   ovh_sms_expediteur    expéditeur validé (« Outsee »),
 *                                              facultatif : sans lui, OVH
 *                                              envoie depuis un numéro court
 *
 * Tant qu'un des quatre premiers manque, smsActif() est faux : en local le
 * code est rendu par l'API pour tester le parcours, en production le numéro
 * est enregistré sans vérification (voir telephoneVerificationRequise()).
 */

require_once __DIR__ . '/config.php';

/** Point d'entrée de l'API OVHcloud Europe. */
const OVH_API_RACINE = 'https://eu.api.ovh.com/1.0';

/** Vrai quand les quatre réglages obligatoires sont présents. */
function smsActif(): bool
{
    foreach (smsReglages() as $cle => $valeur) {
        if ($cle !== 'expediteur' && $valeur === '') {
            return false;
        }
    }

    return true;
}

/** @return array{app_key: string, app_secret: string, consumer_key: string, service: string, expediteur: string} */
function smsReglages(): array
{
    return [
        'app_key'      => trim(reglage('OVH_SMS_APP_KEY', 'ovh_sms_app_key')),
        'app_secret'   => trim(reglage('OVH_SMS_APP_SECRET', 'ovh_sms_app_secret')),
        'consumer_key' => trim(reglage('OVH_SMS_CONSUMER_KEY', 'ovh_sms_consumer_key')),
        'service'      => trim(reglage('OVH_SMS_SERVICE', 'ovh_sms_service')),
        'expediteur'   => trim(reglage('OVH_SMS_EXPEDITEUR', 'ovh_sms_expediteur')),
    ];
}

/**
 * Signature d'une requête OVH : « $1$ » suivi du SHA-1 de
 * secret+clé client+méthode+URL+corps+horodatage, joints par « + ».
 */
function ovhSignature(string $secret, string $cleClient, string $methode, string $url, string $corps, int $horodatage): string
{
    return '$1$' . sha1(implode('+', [$secret, $cleClient, $methode, $url, $corps, (string) $horodatage]));
}

/**
 * Le corps JSON d'un envoi.
 *
 * noStopClause : un code de vérification n'est pas un message commercial,
 * la mention « STOP » n'a pas lieu d'être. Sans expéditeur validé, OVH
 * exige senderForResponse.
 *
 * @return array<string, mixed>
 */
function smsCorps(string $numero, string $message, string $expediteur): array
{
    $corps = [
        'charset'      => 'UTF-8',
        'message'      => $message,
        'receivers'    => [$numero],
        'noStopClause' => true,
        'priority'     => 'high',
        'validityPeriod' => 15,
    ];
    if ($expediteur !== '') {
        $corps['sender'] = $expediteur;
    } else {
        $corps['senderForResponse'] = true;
    }

    return $corps;
}

/**
 * Envoie un SMS ; vrai si OVH l'a accepté.
 *
 * Non configuré : en local le message est seulement journalisé (vrai), en
 * production rien ne part (faux). Les échecs sont journalisés sans le
 * numéro complet.
 */
function smsEnvoyer(string $numero, string $message): bool
{
    if (!smsActif()) {
        if (estEnDeveloppement()) {
            error_log('[Outsee] SMS non configuré, message non envoyé à ' . smsNumeroMasque($numero) . ' : ' . $message);

            return true;
        }

        return false;
    }

    $r     = smsReglages();
    $url   = OVH_API_RACINE . '/sms/' . rawurlencode($r['service']) . '/jobs';
    $corps = (string) json_encode(smsCorps($numero, $message, $r['expediteur']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $temps = time();

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $corps,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-Ovh-Application: ' . $r['app_key'],
            'X-Ovh-Consumer: ' . $r['consumer_key'],
            'X-Ovh-Timestamp: ' . $temps,
            'X-Ovh-Signature: ' . ovhSignature($r['app_secret'], $r['consumer_key'], 'POST', $url, $corps, $temps),
        ],
    ]);
    $reponse = curl_exec($ch);
    $statut  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erreur  = curl_error($ch);
    curl_close($ch);

    if ($reponse === false || $statut < 200 || $statut >= 300) {
        error_log('[Outsee] Envoi SMS refusé (' . $statut . ') vers ' . smsNumeroMasque($numero) . ' : '
            . ($erreur !== '' ? $erreur : mb_substr((string) $reponse, 0, 300)));

        return false;
    }

    return true;
}

/** « +33612345678 » devient « +336••••5678 » : assez pour un journal, pas plus. */
function smsNumeroMasque(string $numero): string
{
    if (strlen($numero) < 8) {
        return '••••';
    }

    return substr($numero, 0, 4) . '••••' . substr($numero, -4);
}

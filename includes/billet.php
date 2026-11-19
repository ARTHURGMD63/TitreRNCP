<?php
/**
 * Le numéro d'un billet : ce qui relie le QR code à un numéro lisible.
 *
 * « OS-000123-3FA2 » : l'identifiant du pass (inscriptions.id) sur six
 * chiffres, puis les quatre premiers caractères de son code QR. Le même
 * numéro est imprimé sous le QR du billet PDF et affiché au scan : l'accueil
 * vérifie d'un coup d'œil que le QR présenté est bien celui de ce billet.
 *
 * Le suffixe change quand un pass annulé est repris (api/inscrire.php donne
 * alors un code neuf) : l'ancien billet imprimé ne correspond plus.
 */

function numeroBillet(int $inscriptionId, string $qrCode): string
{
    $suffixe = strtoupper(substr(preg_replace('/[^0-9a-fA-F]/', '', $qrCode) ?? '', 0, 4));

    return 'OS-' . str_pad((string) $inscriptionId, 6, '0', STR_PAD_LEFT) . ($suffixe !== '' ? '-' . $suffixe : '');
}

<?php
/**
 * Catalogue des styles de musique d'une soirée.
 *
 * « Où sortir ce soir » se décide autant sur la musique que sur le lieu : une
 * soirée techno et un karaoké dans le même bar ne visent pas les mêmes gens,
 * et un étudiant qui déteste le reggaeton a besoin de le savoir avant de
 * réserver, pas en arrivant.
 *
 * Une liste PHP plutôt qu'un ENUM en base, contrairement à `evenements.type` :
 * les styles bougent au rythme des modes, et ajouter « Amapiano » ne doit pas
 * demander une migration de schéma. La colonne est un VARCHAR, et c'est cette
 * liste qui fait foi — tout ce qui vient d'un formulaire passe par
 * `styleMusiqueValide()`.
 */

/**
 * Les styles proposés, dans leur ordre d'affichage.
 *
 * @return array<string, string> code => libellé
 */
function stylesMusique(): array
{
    return [
        'techno'      => 'Techno',
        'house'       => 'House',
        'hiphop'      => 'Hip-hop / Rap',
        'latino'      => 'Latino / Reggaeton',
        'afro'        => 'Afrobeats',
        'pop'         => 'Pop / Variété',
        'rock'        => 'Rock',
        'disco'       => 'Disco / Funk',
        'live'        => 'Concert live',
        'generaliste' => 'Généraliste',
        'sans'        => 'Sans musique',
    ];
}

/** Le code existe-t-il au catalogue ? La chaîne vide vaut « non renseigné ». */
function styleMusiqueValide(?string $code): bool
{
    return $code !== null && $code !== '' && array_key_exists($code, stylesMusique());
}

/**
 * Libellé d'un style, ou chaîne vide si le code est inconnu.
 *
 * Inconnu plutôt qu'absent : une soirée enregistrée avec un style retiré du
 * catalogue depuis doit continuer de s'afficher, sans mention fantaisiste.
 */
function libelleStyleMusique(?string $code): string
{
    return styleMusiqueValide($code) ? stylesMusique()[$code] : '';
}

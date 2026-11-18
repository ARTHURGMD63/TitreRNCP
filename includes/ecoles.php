<?php
/**
 * Catalogue des écoles proposées sur un profil étudiant.
 *
 * Outsee n'est ouvert qu'à Clermont-Ferrand pour l'instant : ce sont ses
 * établissements d'enseignement supérieur. La valeur est enregistrée telle
 * quelle dans users.ecole (et sert au classement « Mon école ») : on ajoute
 * des entrées, on n'en renomme pas. Un compte qui porte une ancienne valeur
 * (« INP Ingénieurs ») la garde : l'app la propose en plus de la liste.
 *
 * Même liste, dans le même ordre, que ECOLES dans mobile/src/catalogue.ts,
 * qui en a besoin avant la connexion (l'inscription).
 */

/** @return list<string> Le catalogue, dans son ordre d'affichage, « Autre » en dernier. */
function ecolesDisponibles(): array
{
    return [
        'UCA',
        'IUT Clermont Auvergne',
        'Polytech Clermont',
        'Clermont Auvergne INP',
        'ISIMA',
        'SIGMA Clermont',
        'INSPÉ Clermont-Auvergne',
        'Clermont School of Business',
        'VetAgro Sup',
        'ENSACF',
        'ESACM',
        'Conservatoire (CRR)',
        'IFSI',
        'Cnam Auvergne',
        'Prépa (CPGE)',
        'BTS',
        'Autre',
    ];
}

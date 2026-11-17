<?php
/**
 * Ancienne page étudiante du site : l'espace étudiant vit dans l'application.
 *
 * Le fichier reste pour que les anciens liens (favoris, e-mails, QR codes)
 * ne tombent pas sur une erreur 404 : un étudiant est renvoyé vers la page
 * « Outsee, c'est dans l'application », un établissement vers son espace.
 */
require_once __DIR__ . '/includes/auth_check.php';
requireStudent();

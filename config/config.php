<?php
declare(strict_types=1);

/**
 * Configuration globale (sans secret).
 * Les identifiants MySQL sont dans config.local.php (exclu de Git).
 * Fuseau : Africa/Abidjan (cahier des charges, section 11).
 */
date_default_timezone_set('Africa/Abidjan');
mb_internal_encoding('UTF-8');

define('APP_NOM', 'Gestion des interventions — Service technique');
define('APP_ENV', 'local'); // passer à 'production' en mise en ligne (HTTPS obligatoire)

define('DSN_MYSQL', 'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4');

// Paramètres validés (section 11 du cahier des charges)
define('SESSION_INACTIVITE_MINUTES', 30);   // inactivité de session
define('CONNEXION_ECHECS_MAX', 5);          // échecs tolérés par e-mail et/ou adresse IP
define('CONNEXION_FENETRE_MINUTES', 15);    // fenêtre d'observation des échecs
define('MOT_DE_PASSE_MIN', 12);             // longueur minimale

if (APP_ENV === 'local') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

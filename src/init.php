<?php
declare(strict_types=1);

/**
 * Point d'amorçage de chaque requête : config, classes, base URL.
 * Tous les scripts publics commencent par : require __DIR__ . '/../src/init.php';
 */
require_once dirname(__DIR__) . '/config/config.php';

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Journal.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/fonctions.php';

if (!defined('BASE_URL')) {
    if (!empty($_SERVER['SCRIPT_NAME'])) {
        // BASE_URL = dossier du script exécuté (public/) — si on est dans admin/, on remonte.
        $dir = str_replace('\\', '/', dirname((string)$_SERVER['SCRIPT_NAME']));
        if (str_ends_with($dir, '/admin')) {
            $dir = dirname($dir);
        }
        define('BASE_URL', rtrim($dir, '/'));
    } else {
        define('BASE_URL', '/gestion-interventions/public'); // contexte CLI
    }
}

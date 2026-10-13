<?php
declare(strict_types=1);

/**
 * Connexion PDO partagée. Une seule instance par requête.
 * Règles du cahier : requêtes préparées partout, exceptions activées,
 * émulation des requêtes préparées désactivée.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $cfg = require dirname(__DIR__) . '/config/config.local.php';
            $dsn = sprintf(DSN_MYSQL, $cfg['hote'], $cfg['port'], $cfg['base']);
            try {
                self::$pdo = new PDO($dsn, $cfg['utilisateur'], $cfg['mot_de_passe'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                error_log('Erreur connexion BD : ' . $e->getMessage());
                http_response_code(500);
                exit('Service momentanément indisponible.');
            }
        }
        return self::$pdo;
    }
}

<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/init.php';
Security::demarrerSession();
$u = Security::utilisateurCourant();
echo json_encode([
    'session_courante' => session_id(),
    'csrf_en_session'  => substr($_SESSION['csrf'] ?? '(aucun)', 0, 16),
]);

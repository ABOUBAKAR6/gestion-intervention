<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/init.php';
Security::demarrerSession();
$u = Security::utilisateurCourant();
if ($u !== null) {
    Auth::deconnecter((int)$u['id']); // journalise puis détruit session et cookie
} else {
    Security::detruireSession();
}
header('Location: ' . BASE_URL . '/login.php');

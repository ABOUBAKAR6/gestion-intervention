<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/init.php';
Security::demarrerSession();
if (Security::utilisateurCourant() !== null) {
    Security::redirect(BASE_URL . '/tableau_bord.php');
}
Security::redirect(BASE_URL . '/login.php');

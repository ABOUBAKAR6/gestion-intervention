<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/init.php';
$u = Security::exigerConnexion();
header('Content-Type: application/json; charset=utf-8');

$groupeId = ($_GET['groupe_id'] ?? '') !== '' ? (int)$_GET['groupe_id'] : 0;
if ($groupeId === 0) {
    // espace personnel : l'utilisateur lui-même
    echo json_encode([['id' => (int)$u['id'], 'label' => $u['prenom'] . ' ' . $u['nom'] . ' (moi)']]);
    exit;
}
if ($u['role_code'] !== 'admin' && !estMembre($groupeId, (int)$u['id'])) {
    echo json_encode([]);
    exit;
}
$q = Database::pdo()->prepare(
    'SELECT u.id, CONCAT(u.prenom, " ", u.nom) AS label
     FROM membres_groupe m JOIN utilisateurs u ON u.id = m.utilisateur_id
     WHERE m.groupe_id = ? AND m.retire_par_id IS NULL AND m.date_retrait IS NULL AND u.actif = 1
     ORDER BY label'
);
$q->execute([$groupeId]);
echo json_encode($q->fetchAll());

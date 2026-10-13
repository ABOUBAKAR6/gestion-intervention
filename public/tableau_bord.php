<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/init.php';
$u = Security::exigerConnexion();
$pdo = Database::pdo();

if ($u['role_code'] === 'admin') {
    $q = $pdo->query('SELECT statut, COUNT(*) c FROM interventions WHERE supprime_le IS NULL GROUP BY statut');
    $parStatut = $q->fetchAll();
    $groupesQ = $pdo->query('SELECT COUNT(*) FROM groupes WHERE archive_le IS NULL');
    $comptesQ = $pdo->query('SELECT COUNT(*), SUM(CASE WHEN actif=1 THEN 1 ELSE 0 END) FROM utilisateurs');
} else {
    $q = $pdo->prepare(
        'SELECT statut, COUNT(*) c FROM interventions
         WHERE supprime_le IS NULL AND (
           (groupe_id IS NULL AND cree_par_id = :uid)
           OR groupe_id IN (SELECT groupe_id FROM membres_groupe WHERE utilisateur_id = :uid AND retire_par_id IS NULL)
         ) GROUP BY statut'
    );
    $q->execute(['uid' => $u['id']]);
    $parStatut = $q->fetchAll();
    $groupesQ = $pdo->prepare(
        'SELECT COUNT(*) FROM groupes g WHERE g.archive_le IS NULL AND g.id IN
         (SELECT groupe_id FROM membres_groupe WHERE utilisateur_id = ? AND retire_par_id IS NULL)'
    );
    $groupesQ->execute([$u['id']]);
    $comptesQ = null;
}
$statuts = array_column($parStatut, 'c', 'statut');
$total = array_sum($statuts);
$nbGroupes = (int)$groupesQ->fetchColumn();
if ($comptesQ) { $comptes = $comptesQ->fetch(PDO::FETCH_NUM); }

$titre = 'Tableau de bord';
require dirname(__DIR__) . '/src/layout/haut.php';
?>
<h1 class="h4 mb-4">Bonjour <?= Security::e($u['prenom'] . ' ' . $u['nom']) ?> 👋</h1>

<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card stat-card stat-marine p-3">
    <i class="bi bi-flag icone"></i>
    <div class="valeur"><?= (int)($statuts['signalee'] ?? 0) ?></div>
    <span class="badge bg-light text-dark mt-1">signalées</span></div></div>
  <div class="col-md-3"><div class="card stat-card stat-or p-3">
    <i class="bi bi-gear-wide-connected icone"></i>
    <div class="valeur"><?= (int)($statuts['en_cours'] ?? 0) ?></div>
    <span class="badge bg-light text-dark mt-1">en cours</span></div></div>
  <div class="col-md-3"><div class="card stat-card stat-vert p-3">
    <i class="bi bi-check2-circle icone"></i>
    <div class="valeur"><?= (int)(($statuts['resolue'] ?? 0) + ($statuts['non_resolue'] ?? 0)) ?></div>
    <span class="badge bg-light text-dark mt-1">finalisées</span></div></div>
  <div class="col-md-3"><div class="card stat-card stat-ambre p-3">
    <i class="bi bi-hourglass-split icone"></i>
    <div class="valeur"><?= (int)($statuts['en_attente_materiel'] ?? 0) ?></div>
    <span class="badge bg-light text-dark mt-1">attente matériel</span></div></div>
</div>

<div class="card p-4 mb-4">
  <div class="d-flex justify-content-between mb-2">
    <h2 class="h6 mb-0">Synthèse</h2>
    <span class="text-muted small">Total interventions visibles : <strong><?= $total ?></strong>
      — groupes : <strong><?= $nbGroupes ?></strong></span>
  </div>
  <a class="btn btn-marine btn-sm" href="<?= BASE_URL ?>/intervention_nouveau.php">+ Nouvelle intervention</a>
</div>

<?php if (($u['role_code'] ?? '') === 'admin' && !empty($comptes)): ?>
<div class="card p-4">
  <h2 class="h6">Administration</h2>
  <p class="small mb-2">Comptes : <strong><?= (int)$comptes[0] ?></strong> (actifs : <strong><?= (int)$comptes[1] ?></strong>)</p>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-primary btn-sm" href="<?= BASE_URL ?>/admin/accueil.php">Page d'administration</a>
    <a class="btn btn-outline-primary btn-sm" href="<?= BASE_URL ?>/admin/journal.php">Journal complet</a>
  </div>
</div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/src/layout/bas.php'; ?>

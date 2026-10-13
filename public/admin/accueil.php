<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/src/init.php';
$u = Security::exigerAdmin();
$pdo = Database::pdo();

/* ---------- Restauration d'un groupe archivé ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restaurer') {
    $gid = (int)($_POST['groupe_id'] ?? 0);
    $pdo->prepare('UPDATE groupes SET archive_le = NULL, archive_motif = NULL, updated_at = NOW()
                   WHERE id = ? AND archive_le IS NOT NULL')
        ->execute([$gid]);
    Journal::log((int)$u['id'], Journal::GROUPE_RESTORE, 'groupe', $gid, $gid);
    Security::flash('succes', 'Groupe restauré.');
    Security::redirect(BASE_URL . '/admin/accueil.php');
}

/* ---------- Statistiques de supervision ---------- */
$stats = $pdo->query(
    'SELECT
       (SELECT COUNT(*) FROM utilisateurs) AS comptes,
       (SELECT COUNT(*) FROM utilisateurs WHERE actif = 1) AS actifs,
       (SELECT COUNT(*) FROM groupes WHERE archive_le IS NULL) AS groupes_actifs,
       (SELECT COUNT(*) FROM groupes WHERE archive_le IS NOT NULL) AS groupes_archives,
       (SELECT COUNT(*) FROM interventions WHERE supprime_le IS NULL) AS interventions,
       (SELECT COUNT(*) FROM journal_activites) AS evenements'
)->fetch();

$archives = $pdo->query(
    'SELECT g.*, CONCAT(c.prenom," ",c.nom) AS createur_nom
     FROM groupes g JOIN utilisateurs c ON c.id = g.createur_id
     WHERE g.archive_le IS NOT NULL ORDER BY g.archive_le DESC'
)->fetchAll();

$titre = 'Administration';
require dirname(__DIR__, 2) . '/src/layout/haut.php';
?>
<h1 class="h4 mb-3">Administration</h1>

<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="card p-3"><div class="h4 mb-0"><?= (int)$stats['comptes'] ?></div>
    comptes — <?= (int)$stats['actifs'] ?> actifs</div></div>
  <div class="col-md-4"><div class="card p-3"><div class="h4 mb-0"><?= (int)$stats['groupes_actifs'] ?></div>
    groupes actifs (<?= (int)$stats['groupes_archives'] ?> archivés)</div></div>
  <div class="col-md-4"><div class="card p-3"><div class="h4 mb-0"><?= (int)$stats['interventions'] ?></div>
    interventions — <?= (int)$stats['evenements'] ?> événements journalisés</div></div>
</div>

<div class="row g-3">
  <div class="col-md-6">
    <div class="card p-4 mb-3">
      <h2 class="h6 mb-3">Gestion</h2>
      <div class="d-grid gap-2">
        <a class="btn btn-outline-primary btn-sm" href="<?= BASE_URL ?>/admin/comptes.php">Comptes (création, désactivation, réinitialisation)</a>
        <a class="btn btn-outline-primary btn-sm" href="<?= BASE_URL ?>/admin/referentiels.php">Référentiels (services, types)</a>
        <a class="btn btn-outline-primary btn-sm" href="<?= BASE_URL ?>/admin/journal.php">Journal d'activités complet</a>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card p-4 mb-3">
      <h2 class="h6 mb-3">Groupes archivés (restauration possible)</h2>
      <?php if (!$archives): ?><p class="text-muted small mb-0">Aucun groupe archivé.</p><?php endif; ?>
      <?php foreach ($archives as $g): ?>
        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
          <div class="small">
            <strong><?= Security::e($g['nom']) ?></strong>
            <div class="text-muted">archivé le <?= Security::e($g['archive_le']) ?> — motif : <?= Security::e($g['archive_motif']) ?></div>
          </div>
          <form method="post">
            <?= Security::csrfField() ?>
            <input type="hidden" name="action" value="restaurer">
            <input type="hidden" name="groupe_id" value="<?= (int)$g['id'] ?>">
            <button class="btn btn-sm btn-outline-success">Restaurer</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/bas.php'; ?>

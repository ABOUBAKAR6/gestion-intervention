<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/init.php';
$u = Security::exigerConnexion();
$pdo = Database::pdo();
Security::csrfVerifier();

/* ---------- Création de groupe : le créateur devient automatiquement créateur + chef (RG5) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'creer') {
    $nom = trim((string)($_POST['nom'] ?? ''));
    if ($nom !== '' && mb_strlen($nom) <= 120) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO groupes (nom, createur_id) VALUES (?, ?)')
                ->execute([$nom, $u['id']]);
            $gid = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO membres_groupe (groupe_id, utilisateur_id, est_chef, ajoute_par_id)
                           VALUES (?, ?, 1, ?)')
                ->execute([$gid, $u['id'], $u['id']]);
            $pdo->commit();
            Journal::log((int)$u['id'], Journal::GROUPE_CREE, 'groupe', $gid, $gid, 'Nom : ' . $nom);
            Security::flash('succes', 'Groupe « ' . $nom . ' » créé. Vous en êtes le chef.');
        } catch (Throwable $ex) {
            $pdo->rollBack();
            error_log('Création groupe : ' . $ex->getMessage());
            Security::flash('erreur', 'Erreur lors de la création du groupe.');
        }
    }
    Security::redirect(BASE_URL . '/groupes.php');
}

$q = $pdo->prepare(
    'SELECT g.*, (SELECT COUNT(*) FROM membres_groupe m
        WHERE m.groupe_id = g.id AND m.retire_par_id IS NULL AND m.date_retrait IS NULL) AS nb_membres
     FROM groupes g
     WHERE g.archive_le IS NULL AND g.id IN
       (SELECT groupe_id FROM membres_groupe WHERE utilisateur_id = ? AND retire_par_id IS NULL)
     ORDER BY g.nom'
);
$q->execute([$u['id']]);
$mesGroupes = $q->fetchAll();

if ($u['role_code'] === 'admin') {
    $autres = $pdo->prepare(
        'SELECT g.*, (SELECT COUNT(*) FROM membres_groupe m
            WHERE m.groupe_id = g.id AND m.retire_par_id IS NULL AND m.date_retrait IS NULL) AS nb_membres
         FROM groupes g WHERE g.archive_le IS NULL AND g.id NOT IN
           (SELECT groupe_id FROM membres_groupe WHERE utilisateur_id = ?)
         ORDER BY g.nom'
    );
    $autres->execute([$u['id']]);
    $autres = $autres->fetchAll();
}

$titre = 'Groupes';
require dirname(__DIR__) . '/src/layout/haut.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Mes groupes</h1>
</div>

<div class="card p-0 mb-4">
<table class="table mb-0 align-middle">
<thead><tr><th>Groupe</th><th>Membres</th><th>Rôle du créateur</th><th></th></tr></thead>
<tbody>
<?php if (!$mesGroupes): ?>
  <tr><td colspan="4" class="text-center text-muted py-4">Vous ne participez à aucun groupe. créez-en un ci-dessous.</td></tr>
<?php endif; ?>
<?php foreach ($mesGroupes as $g): ?>
<tr>
  <td><strong><?= Security::e($g['nom']) ?></strong>
      <?php if (estChef((int)$g['id'], (int)$u['id'])): ?><span class="badge bg-warning text-dark">Chef</span><?php endif; ?></td>
  <td class="small"><?= (int)$g['nb_membres'] ?> membre(s)</td>
  <td class="small"><?= (int)$g['createur_id'] === (int)$u['id'] ? 'Créateur' : 'Membre' ?></td>
  <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/groupe_detail.php?id=<?= (int)$g['id'] ?>">Ouvrir</a></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>

<div class="card p-4 mb-4" style="max-width:480px">
  <h2 class="h6">Créer un groupe</h2>
  <p class="small text-muted">Vous deviendrez automatiquement le créateur et le chef de ce groupe (RG5).</p>
  <form method="post">
    <?= Security::csrfField() ?>
    <input type="hidden" name="action" value="creer">
    <div class="input-group">
      <input type="text" name="nom" class="form-control" placeholder="Nom du groupe" required maxlength="120">
      <button class="btn btn-marine">Créer</button>
    </div>
  </form>
</div>

<?php if ($u['role_code'] === 'admin' && !empty($autres)): ?>
<h1 class="h5 mb-3 text-muted">Groupes supervisés (admin)</h1>
<div class="card p-0">
<table class="table mb-0">
<thead><tr><th>Groupe</th><th>Membres</th><th></th></tr></thead>
<tbody>
<?php foreach ($autres as $g): ?>
<tr>
  <td class="small"><?= Security::e($g['nom']) ?></td>
  <td class="small"><?= (int)$g['nb_membres'] ?></td>
  <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/groupe_detail.php?id=<?= (int)$g['id'] ?>">Superviser</a></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
<?php require dirname(__DIR__) . '/src/layout/bas.php'; ?>

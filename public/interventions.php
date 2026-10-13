<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/init.php';
$u = Security::exigerConnexion();
$pdo = Database::pdo();

$fStatut   = (string)($_GET['statut'] ?? '');
$fService  = ($_GET['service'] ?? '') !== '' ? (int)$_GET['service'] : null;
$fBranche  = ($_GET['branche'] ?? '') !== '' ? (int)$_GET['branche'] : null;

$sql = 'SELECT i.*, s.nom AS service_nom, t.nom AS type_nom, CONCAT(u.prenom," ",u.nom) AS technicien
        FROM interventions i
        LEFT JOIN services_demandeurs s ON s.id = i.service_demandeur_id
        LEFT JOIN types_intervention t  ON t.id = i.type_intervention_id
        LEFT JOIN utilisateurs u        ON u.id = i.technicien_id
        WHERE i.supprime_le IS NULL';
$params = [];

if ($u['role_code'] !== 'admin') {
    $sql .= ' AND ( (i.groupe_id IS NULL AND i.cree_par_id = :uid)
             OR i.groupe_id IN (SELECT groupe_id FROM membres_groupe WHERE utilisateur_id = :uid AND retire_par_id IS NULL) )';
    $params['uid'] = $u['id'];
}
if ($fStatut !== '' && isset(LIBELLES_STATUT[$fStatut])) {
    $sql .= ' AND i.statut = :st';  $params['st'] = $fStatut;
}
if ($fService) { $sql .= ' AND i.service_demandeur_id = :sv'; $params['sv'] = $fService; }
if ($fBranche) { $sql .= ' AND i.type_intervention_id = :ty'; $params['ty'] = $fBranche; }
$sql .= ' ORDER BY i.created_at DESC LIMIT 200';

$q = $pdo->prepare($sql);
$q->execute($params);
$liste = $q->fetchAll();

$services = $pdo->query('SELECT id, nom FROM services_demandeurs WHERE actif=1 ORDER BY nom')->fetchAll();
$branches = $pdo->query('SELECT id, nom FROM types_intervention WHERE actif=1 ORDER BY nom')->fetchAll();

$titre = 'Interventions';
require dirname(__DIR__) . '/src/layout/haut.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Interventions</h1>
  <a class="btn btn-marine btn-sm" href="<?= BASE_URL ?>/intervention_nouveau.php">+ Nouvelle</a>
</div>

<form class="card p-3 mb-3 row g-2 flex-row" method="get">
  <div class="col-md-3">
    <select name="statut" class="form-select form-select-sm">
      <option value="">Tous les statuts</option>
      <?php foreach (LIBELLES_STATUT as $c => $l): ?>
        <option value="<?= $c ?>" <?= $fStatut === $c ? 'selected' : '' ?>><?= Security::e($l) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3">
    <select name="service" class="form-select form-select-sm">
      <option value="">Tous les services</option>
      <?php foreach ($services as $s): ?>
        <option value="<?= $s['id'] ?>" <?= $fService === (int)$s['id'] ? 'selected' : '' ?>><?= Security::e($s['nom']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3">
    <select name="branche" class="form-select form-select-sm">
      <option value="">Toutes les branches</option>
      <?php foreach ($branches as $b): ?>
        <option value="<?= $b['id'] ?>" <?= $fBranche === (int)$b['id'] ? 'selected' : '' ?>><?= Security::e($b['nom']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3 d-flex gap-2">
    <button class="btn btn-sm btn-outline-primary">Filtrer</button>
    <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/interventions.php">Réinitialiser</a>
  </div>
</form>

<div class="card p-0">
<table class="table table-hover mb-0 align-middle">
<thead><tr><th>Réf.</th><th>Service</th><th>Problème</th><th>Priorité</th><th>Statut</th><th>Technicien</th><th></th></tr></thead>
<tbody>
<?php if (!$liste): ?>
  <tr><td colspan="7" class="text-center text-muted py-4">Aucune intervention.</td></tr>
<?php endif; ?>
<?php foreach ($liste as $i): ?>
<tr>
  <td class="small fw-bold"><?= Security::e($i['reference']) ?></td>
  <td class="small"><?= Security::e($i['service_nom'] ?: $i['service_autre']) ?></td>
  <td class="small"><?= Security::e(mb_strimwidth($i['description_probleme'], 0, 60, '…')) ?></td>
  <td><span class="badge bg-<?= $i['priorite'] === 'urgente' ? 'danger' : ($i['priorite'] === 'haute' ? 'warning text-dark' : ($i['priorite'] === 'basse' ? 'secondary' : 'light text-dark')) ?>">
        <?= Security::e(LIBELLES_PRIORITE[$i['priorite']]) ?></span></td>
  <td><span class="badge bg-<?= CLASSE_STATUT[$i['statut']] ?>"><?= Security::e(LIBELLES_STATUT[$i['statut']]) ?></span></td>
  <td class="small"><?= Security::e($i['technicien'] ?: '—') ?></td>
  <td class="text-nowrap">
    <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/intervention_detail.php?id=<?= (int)$i['id'] ?>">Détail</a>
    <?php if ((int)$i['cree_par_id'] === (int)$u['id'] || $u['role_code'] === 'admin'): ?>
      <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/intervention_editer.php?id=<?= (int)$i['id'] ?>">Modifier</a>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php require dirname(__DIR__) . '/src/layout/bas.php'; ?>

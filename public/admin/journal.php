<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/src/init.php';
$u = Security::exigerAdmin();
$pdo = Database::pdo();

$fAction = trim((string)($_GET['action'] ?? ''));
$fAuteur = ($_GET['auteur'] ?? '') !== '' ? (int)$_GET['auteur'] : null;

$sql = 'SELECT j.*, CONCAT(p.prenom," ",p.nom) AS auteur FROM journal_activites j
        LEFT JOIN utilisateurs p ON p.id = j.utilisateur_id WHERE 1=1';
$par = [];
if ($fAction !== '') { $sql .= ' AND j.action LIKE :act'; $par[':act'] = '%' . $fAction . '%'; }
if ($fAuteur)        { $sql .= ' AND j.utilisateur_id = :aut'; $par[':aut'] = $fAuteur; }
$sql .= ' ORDER BY j.created_at DESC LIMIT 300';
$q = $pdo->prepare($sql);
$q->execute($par);
$journal = $q->fetchAll();

$auteurs = $pdo->query('SELECT id, CONCAT(prenom," ",nom) AS n FROM utilisateurs ORDER BY n')->fetchAll();

$titre = "Journal d'activités";
require dirname(__DIR__, 2) . '/src/layout/haut.php';
?>
<h1 class="h4 mb-2">Journal d'activités</h1>
<p class="small text-muted">Table en ajout seulement : ni modifiable ni supprimable depuis l'application (RG11). Aucun mot de passe ni secret n'y figure.</p>

<form class="card p-3 mb-3 row g-2 flex-row" method="get">
  <div class="col-md-4"><input type="text" name="action" class="form-control form-control-sm"
      placeholder="Filtrer par action (ex. intervention_)" value="<?= Security::e($fAction) ?>"></div>
  <div class="col-md-4"><select name="auteur" class="form-select form-select-sm">
      <option value="">Tous les auteurs</option>
      <?php foreach ($auteurs as $a): ?>
        <option value="<?= (int)$a['id'] ?>" <?= $fAuteur === (int)$a['id'] ? 'selected' : '' ?>><?= Security::e($a['n']) ?></option>
      <?php endforeach; ?>
    </select></div>
  <div class="col-md-2"><button class="btn btn-sm btn-outline-primary">Filtrer</button></div>
</form>

<div class="card p-0">
<table class="table table-sm mb-0">
<thead><tr><th>Date</th><th>Auteur</th><th>Action</th><th>Objet</th><th>Groupe</th><th>Détails</th><th>IP</th></tr></thead>
<tbody>
<?php if (!$journal): ?><tr><td colspan="7" class="text-center text-muted py-4">Aucun événement.</td></tr><?php endif; ?>
<?php foreach ($journal as $ev): ?>
<tr class="small">
  <td class="text-nowrap"><?= Security::e($ev['created_at']) ?></td>
  <td><?= Security::e($ev['auteur'] ?: '— (connexion échouée)') ?></td>
  <td><span class="badge bg-light text-dark"><?= Security::e($ev['action']) ?></span></td>
  <td class="small"><?= Security::e($ev['objet_type'] ?: '—') ?><?= $ev['objet_id'] ? ' #' . (int)$ev['objet_id'] : '' ?></td>
  <td><?= $ev['groupe_id'] ? (int)$ev['groupe_id'] : '—' ?></td>
  <td class="small"><?= Security::e($ev['details'] ?: '') ?></td>
  <td class="small text-muted"><?= Security::e($ev['adresse_ip'] ?: '') ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/bas.php'; ?>

<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/init.php';
$u = Security::exigerConnexion();
$pdo = Database::pdo();
Security::csrfVerifier();

$q = $pdo->prepare('SELECT * FROM interventions WHERE id = ? AND supprime_le IS NULL');
$q->execute([(int)($_GET['id'] ?? $_POST['id'] ?? 0)]);
$int = $q->fetch();
if (!$int || !peutAccederIntervention($u, $int)) {
    http_response_code(403);
    exit('Accès refusé.');
}
// Modification réservée à l'auteur ou à l'administrateur
if ((int)$int['cree_par_id'] !== (int)$u['id'] && $u['role_code'] !== 'admin') {
    http_response_code(403);
    exit('Seul l\'auteur ou l\'administrateur peut modifier cette intervention.');
}

$services = $pdo->query('SELECT id, nom FROM services_demandeurs WHERE actif=1 ORDER BY nom')->fetchAll();
$branches = $pdo->query('SELECT id, nom FROM types_intervention WHERE actif=1 ORDER BY nom')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $svcId  = ($_POST['service_id'] ?? '') !== '' ? (int)$_POST['service_id'] : null;
    $svcAut = trim((string)($_POST['service_autre'] ?? ''));
    $tyId   = ($_POST['type_id'] ?? '') !== '' ? (int)$_POST['type_id'] : null;
    $tyAut  = trim((string)($_POST['type_autre'] ?? ''));
    // RG14 en édition : liste OU autre (les deux à NULL => validation refusée)
    if ($svcId !== null) { $svcAut = null; } elseif ($svcAut === '') { $svcId = null; $svcAut = null; }
    if ($tyId  !== null) { $tyAut  = null; } elseif ($tyAut  === '') { $tyId  = null; $tyAut  = null; }

    $set = '';
    $par = [];
    $champs = ['nom_demandeur', 'telephone_demandeur', 'description_probleme', 'priorite'];
    foreach ($champs as $c) { $set .= "$c = :$c, "; $par[":$c"] = trim((string)($_POST[$c] ?? '')); }
    if ($int['service_demandeur_id'] != $svcId || $int['service_autre'] != $svcAut) { $set .= 'service_demandeur_id=:svid, service_autre=:svaut, '; $par[':svid'] = $svcId; $par[':svaut'] = $svcAut; }
    if ($int['type_intervention_id'] != $tyId || $int['type_autre'] != $tyAut) { $set .= 'type_intervention_id=:tyid, type_autre=:tyaut, '; $par[':tyid'] = $tyId; $par[':tyaut'] = $tyAut; }

    $pdo->prepare('UPDATE interventions SET ' . rtrim($set, ', ') . ', modifie_par_id = :uid WHERE id = :id')
        ->execute($par + [':uid' => $u['id'], ':id' => $int['id']]);
    Journal::log((int)$u['id'], Journal::INTERV_MODIFIEE, 'intervention', (int)$int['id'],
        $int['groupe_id'], 'Réf. ' . $int['reference']);
    Security::flash('succes', 'Intervention modifiée.');
    Security::redirect(BASE_URL . '/intervention_detail.php?id=' . (int)$int['id']);
}
$titre = 'Modifier ' . $int['reference'];
require dirname(__DIR__) . '/src/layout/haut.php';
?>
<h1 class="h4 mb-3">Modifier <?= Security::e($int['reference']) ?></h1>
<form method="post" class="card p-4">
  <?= Security::csrfField() ?>
  <input type="hidden" name="id" value="<?= (int)$int['id'] ?>">
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label">Service demandeur (liste)</label>
      <select name="service_id" class="form-select">
        <option value="">— Autre —</option>
        <?php foreach ($services as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $int['service_demandeur_id'] == $s['id'] ? 'selected' : '' ?>><?= Security::e($s['nom']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-6"><label class="form-label">… ou texte libre (si « Autre »)</label>
      <input type="text" name="service_autre" class="form-control" value="<?= Security::e($int['service_autre'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">Branche technique (liste)</label>
      <select name="type_id" class="form-select">
        <option value="">— Autre —</option>
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $int['type_intervention_id'] == $b['id'] ? 'selected' : '' ?>><?= Security::e($b['nom']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-6"><label class="form-label">… ou texte libre (si « Autre »)</label>
      <input type="text" name="type_autre" class="form-control" value="<?= Security::e($int['type_autre'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">Nom du demandeur</label>
      <input type="text" name="nom_demandeur" class="form-control" required value="<?= Security::e($int['nom_demandeur']) ?>"></div>
    <div class="col-md-6"><label class="form-label">Téléphone du demandeur</label>
      <input type="text" name="telephone_demandeur" class="form-control" required value="<?= Security::e($int['telephone_demandeur']) ?>"></div>
    <div class="col-md-4"><label class="form-label">Priorité</label>
      <select name="priorite" class="form-select">
        <?php foreach (LIBELLES_PRIORITE as $c => $l): ?>
          <option value="<?= $c ?>" <?= $int['priorite'] === $c ? 'selected' : '' ?>><?= Security::e($l) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-12"><label class="form-label">Description</label>
      <textarea name="description_probleme" class="form-control" rows="3" required><?= Security::e($int['description_probleme']) ?></textarea></div>
  </div>
  <button class="btn btn-marine mt-3">Enregistrer</button>
  <a class="btn btn-outline-secondary mt-3" href="<?= BASE_URL ?>/intervention_detail.php?id=<?= (int)$int['id'] ?>">Annuler</a>
</form>
<?php require dirname(__DIR__) . '/src/layout/bas.php'; ?>

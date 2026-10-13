<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/init.php';
$u = Security::exigerConnexion();
$pdo = Database::pdo();
Security::csrfVerifier();

$groupesMembres = $pdo->prepare("SELECT g.id, g.nom FROM groupes g
     WHERE g.archive_le IS NULL AND (g.id IN (SELECT groupe_id FROM membres_groupe WHERE utilisateur_id=? AND retire_par_id IS NULL) OR ? = 'admin')
     ORDER BY g.nom");
$groupesMembres->execute([$u['id'], $u['role_code']]);
$mesGroupes = $groupesMembres->fetchAll();

$services = $pdo->query('SELECT id, nom FROM services_demandeurs WHERE actif=1 ORDER BY nom')->fetchAll();
$branches = $pdo->query('SELECT id, nom FROM types_intervention WHERE actif=1 ORDER BY nom')->fetchAll();
$membres  = []; // candidats techniciens, recalculés si groupe choisi

$erreurs = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $groupeId        = ($_POST['groupe_id'] ?? '') !== '' ? (int)$_POST['groupe_id'] : null;
    $svcChoix        = $_POST['service_choix'] ?? '';      // 'liste' | 'autre'
    $svcId           = ($_POST['service_id'] ?? '') !== '' ? (int)$_POST['service_id'] : null;
    $svcAutre        = trim((string)($_POST['service_autre'] ?? ''));
    $tyChoix         = $_POST['type_choix'] ?? '';
    $tyId            = ($_POST['type_id'] ?? '') !== '' ? (int)$_POST['type_id'] : null;
    $tyAutre         = trim((string)($_POST['type_autre'] ?? ''));
    $nom             = trim((string)($_POST['nom_demandeur'] ?? ''));
    $tel             = trim((string)($_POST['telephone_demandeur'] ?? ''));
    $desc            = trim((string)($_POST['description_probleme'] ?? ''));
    $priorite        = (string)($_POST['priorite'] ?? 'normale');
    $technicienId    = ($_POST['technicien_id'] ?? '') !== '' ? (int)$_POST['technicien_id'] : null;

    // Validation RG14 : liste OU texte, jamais les deux, jamais aucun
    if ($svcChoix === 'liste' && $svcId)        { $svcAutre = null; }
    elseif ($svcChoix === 'autre' && $svcAutre !== '') { $svcId = null; }
    else { $erreurs[] = 'Choisissez un service dans la liste OU saisissez un texte « Autre » (un seul des deux).'; }

    if ($tyChoix === 'liste' && $tyId)          { $tyAutre = null; }
    elseif ($tyChoix === 'autre' && $tyAutre !== '') { $tyId = null; }
    else { $erreurs[] = 'Choisissez une branche technique dans la liste OU saisissez un texte « Autre » (un seul des deux).'; }

    if ($nom === '')  { $erreurs[] = 'Le nom du demandeur est obligatoire.'; }
    if ($tel === '')  { $erreurs[] = 'Le téléphone du demandeur est obligatoire.'; }
    if ($desc === '') { $erreurs[] = 'La description du problème est obligatoire.'; }
    if (!isset(LIBELLES_PRIORITE[$priorite])) { $priorite = 'normale'; }

    // RG15 : le technicien doit être membre actif du groupe, ou l'utilisateur lui-même si espace perso
    if ($groupeId !== null) {
        if ($u['role_code'] !== 'admin' && !estMembre($groupeId, (int)$u['id'])) {
            $erreurs[] = 'Vous ne pouvez créer une intervention que dans vos groupes.';
        }
        if ($technicienId !== null && !estMembre($groupeId, $technicienId)) {
            $erreurs[] = 'Le technicien désigné doit être membre actif du groupe (RG15).';
        }
    } elseif ($technicienId !== null && $technicienId !== (int)$u['id']) {
        $erreurs[] = 'Dans votre espace personnel, seul vous-même pouvez être désigné technicien (RG15).';
    }

    if (!$erreurs) {
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare(
                'INSERT INTO interventions (groupe_id, service_demandeur_id, service_autre, type_intervention_id, type_autre,
                    nom_demandeur, telephone_demandeur, description_probleme, priorite, technicien_id, cree_par_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            );
            $ins->execute([$groupeId, $svcId, $svcAutre, $tyId, $tyAutre, $nom, $tel, $desc, $priorite, $technicienId, $u['id']]);
            $id = (int)$pdo->lastInsertId();
            $ref = attribuerReference($pdo, $id);
            $pdo->prepare('UPDATE interventions SET reference=? WHERE id=?')->execute([$ref, $id]);
            $pdo->prepare('INSERT INTO historique_statuts (intervention_id, ancien_statut, nouveau_statut, change_par_id)
                           VALUES (?, NULL, "signalee", ?)')->execute([$id, $u['id']]);
            $pdo->commit();
            Journal::log((int)$u['id'], Journal::INTERV_CREES, 'intervention', $id, $groupeId, 'Réf. ' . $ref);
            Security::flash('succes', 'Intervention ' . $ref . ' créée.');
            Security::redirect(BASE_URL . '/intervention_detail.php?id=' . $id);
        } catch (Throwable $ex) {
            $pdo->rollBack();
            error_log('Création intervention : ' . $ex->getMessage());
            Security::flash('erreur', 'Erreur technique lors de la création. Réessayez.');
        }
    }
}
$titre = 'Nouvelle intervention';
require dirname(__DIR__) . '/src/layout/haut.php';
?>
<h1 class="h4 mb-3">Créer une intervention</h1>
<?php foreach ($erreurs as $err): ?>
  <div class="alert alert-danger py-2"><?= Security::e($err) ?></div>
<?php endforeach; ?>
<form method="post" class="card p-4">
  <?= Security::csrfField() ?>
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Espace</label>
      <select name="groupe_id" class="form-select" id="choixGroupe">
        <option value="">— Mon espace personnel —</option>
        <?php foreach ($mesGroupes as $g): ?>
          <option value="<?= $g['id'] ?>" <?= ($_POST['groupe_id'] ?? '') == $g['id'] ? 'selected' : '' ?>><?= Security::e($g['nom']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">Espace personnel : visible de vous seul (et de l'administrateur).</div>
    </div>
    <div class="col-md-6">
      <label class="form-label">Priorité</label>
      <select name="priorite" class="form-select">
        <?php foreach (LIBELLES_PRIORITE as $c => $l): ?>
          <option value="<?= $c ?>" <?= ($_POST['priorite'] ?? 'normale') === $c ? 'selected' : '' ?>><?= Security::e($l) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <hr class="my-4">
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label d-block">Service demandeur</label>
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="radio" name="service_choix" value="liste" checked
               onchange="bascule('svcListe','svcAutre')">
        <label class="form-check-label">Dans la liste</label>
      </div>
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="radio" name="service_choix" value="autre" onchange="bascule('svcAutre','svcListe')">
        <label class="form-check-label">Autre (texte libre)</label>
      </div>
      <select name="service_id" id="svcListe" class="form-select mt-2">
        <option value="">— Choisir un service —</option>
        <?php foreach ($services as $s): ?>
          <option value="<?= $s['id'] ?>" <?= ($_POST['service_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= Security::e($s['nom']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="service_autre" id="svcAutre" class="form-control mt-2" style="display:none"
             placeholder="Nom du service (texte libre)" value="<?= Security::e((string)($_POST['service_autre'] ?? '')) ?>">
    </div>
    <div class="col-md-6" id="blocType">
      <label class="form-label d-block">Branche du service technique</label>
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="radio" name="type_choix" value="liste" checked onchange="bascule('tyListe','tyAutre')">
        <label class="form-check-label">Dans la liste</label>
      </div>
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="radio" name="type_choix" value="autre" onchange="bascule('tyAutre','tyListe')">
        <label class="form-check-label">Autre (texte libre)</label>
      </div>
      <select name="type_id" id="tyListe" class="form-select mt-2">
        <option value="">— Choisir une branche —</option>
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['id'] ?>" <?= ($_POST['type_id'] ?? '') == $b['id'] ? 'selected' : '' ?>><?= Security::e($b['nom']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="type_autre" id="tyAutre" class="form-control mt-2" style="display:none"
             placeholder="Branche (texte libre)" value="<?= Security::e((string)($_POST['type_autre'] ?? '')) ?>">
    </div>
  </div>

  <hr class="my-4">
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label">Nom de la personne qui a appelé *</label>
      <input type="text" name="nom_demandeur" class="form-control" required value="<?= Security::e((string)($_POST['nom_demandeur'] ?? '')) ?>"></div>
    <div class="col-md-6"><label class="form-label">Téléphone du demandeur *</label>
      <input type="text" name="telephone_demandeur" class="form-control" required value="<?= Security::e((string)($_POST['telephone_demandeur'] ?? '')) ?>"></div>
    <div class="col-12"><label class="form-label">Description du problème * <span class="text-muted small">(aucune donnée de patient — RG19)</span></label>
      <textarea name="description_probleme" class="form-control" rows="3" required><?= Security::e((string)($_POST['description_probleme'] ?? '')) ?></textarea></div>
    <div class="col-md-6"><label class="form-label">Technicien désigné (membre actif ou vous-même)</label>
      <select name="technicien_id" class="form-select"><option value="">— À définir plus tard —</option></select>
      <div class="form-text">La liste se remplit selon le groupe choisi.</div></div>
  </div>
  <button class="btn btn-marine mt-4">Créer l'intervention</button>
</form>

<script>
function bascule(montre, cache) {
  document.getElementById(montre).style.display = '';
  document.getElementById(cache).style.display  = 'none';
}
// LDrag dynamique du technicien selon le groupe
document.getElementById('choixGroupe').addEventListener('change', function () {
  fetch('<?= BASE_URL ?>/api_membres.php?groupe_id=' + encodeURIComponent(this.value))
    .then(r => r.json()).then(l => {
      const sel = document.querySelector('select[name="technicien_id"]');
      sel.innerHTML = '<option value="">— À définir plus tard —</option>';
      l.forEach(m => sel.insertAdjacentHTML('beforeend', `<option value="${m.id}">${m.label}</option>`));
    });
});
</script>
<?php require dirname(__DIR__) . '/src/layout/bas.php'; ?>

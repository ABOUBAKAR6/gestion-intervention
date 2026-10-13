<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/src/init.php';
$u = Security::exigerAdmin();
$pdo = Database::pdo();
Security::csrfVerifier();

/* ---------- Ajout / masquage dans les référentiels communs (RG13) ---------- */
foreach (['services_demandeurs' => 'service', 'types_intervention' => 'type'] as $table => $cle) {
    if (($_POST['action'] ?? '') === 'ajouter_' . $cle) {
        $nom = trim((string)($_POST['nom'] ?? ''));
        if ($nom !== '' && mb_strlen($nom) <= 100) {
            try {
                $pdo->prepare("INSERT INTO $table (nom) VALUES (?)")->execute([$nom]);
                Journal::log((int)$u['id'], Journal::REFERENTIEL_CRUE, $cle, null, null, 'Nom : ' . $nom);
                Security::flash('succes', '« ' . $nom . ' » ajouté au référentiel.');
            } catch (PDOException $ex) {
                Security::flash('erreur', 'Élément déjà existant.');
            }
        }
        Security::redirect(BASE_URL . '/admin/referentiels.php');
    }
    if (($_POST['action'] ?? '') === 'bascule_' . $cle) {
        $id = (int)($_POST['id'] ?? 0);
        // Masquer sans supprimer (l'historique des interventions reste cohérent)
        $pdo->prepare("UPDATE $table SET actif = NOT actif WHERE id = ?")->execute([$id]);
        Security::redirect(BASE_URL . '/admin/referentiels.php');
    }
}

$services = $pdo->query('SELECT * FROM services_demandeurs ORDER BY actif DESC, nom')->fetchAll();
$types    = $pdo->query('SELECT * FROM types_intervention ORDER BY actif DESC, nom')->fetchAll();

$titre = 'Référentiels';
require dirname(__DIR__, 2) . '/src/layout/haut.php';
?>
<h1 class="h4 mb-3">Référentiels communs</h1>
<p class="small text-muted">Communs à toute l'application, gérés par l'administrateur. À la saisie, l'utilisateur choisit dans la liste ou utilise l'option « Autre » (RG13). Désactiver masque sans supprimer.</p>

<div class="row g-3">
<?php foreach ([['services' => ['table' => 'services_demandeurs', 'cle' => 'service', 'titre' => 'Services demandeurs', 'lignes' => $services]],
                ['types' => ['table' => 'types_intervention', 'cle' => 'type', 'titre' => 'Types d\'intervention', 'lignes' => $types]]] as $grp):
  $t = reset($grp);
?>
  <div class="col-md-6">
    <div class="card p-4 mb-3">
      <h2 class="h6 mb-3"><?= Security::e($t['titre']) ?></h2>
      <?php foreach ($t['lignes'] as $l): ?>
        <div class="d-flex justify-content-between align-items-center border-bottom py-1">
          <span class="small <?= (int)$l['actif'] === 1 ? '' : 'text-muted text-decoration-line-through' ?>">
            <?= Security::e($l['nom']) ?></span>
          <form method="post" class="d-inline">
            <?= Security::csrfField() ?>
            <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
            <input type="hidden" name="action" value="bascule_<?= Security::e($t['cle']) ?>">
            <button class="btn btn-sm btn-outline-<?= (int)$l['actif'] === 1 ? 'secondary' : 'success' ?>">
              <?= (int)$l['actif'] === 1 ? 'Masquer' : 'Réactiver' ?></button>
          </form>
        </div>
      <?php endforeach; ?>
      <form method="post" class="input-group mt-3">
        <?= Security::csrfField() ?>
        <input type="hidden" name="action" value="ajouter_<?= Security::e($t['cle']) ?>">
        <input type="text" name="nom" class="form-control" placeholder="Nouvel élément" required maxlength="100">
        <button class="btn btn-marine">Ajouter</button>
      </form>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/bas.php'; ?>

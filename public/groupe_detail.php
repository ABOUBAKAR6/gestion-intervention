<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/init.php';
$u = Security::exigerConnexion();
$pdo = Database::pdo();
Security::csrfVerifier();

$gid = (int)($_GET['id'] ?? $_POST['groupe_id'] ?? 0);
$q = $pdo->prepare('SELECT g.*, CONCAT(cr.prenom," ",cr.nom) AS createur_nom FROM groupes g
                    JOIN utilisateurs cr ON cr.id = g.createur_id WHERE g.id = ?');
$q->execute([$gid]);
$groupe = $q->fetch();
if (!$groupe) { http_response_code(404); exit('Groupe introuvable.'); }

$peutGerer   = peutGererGroupe($u, $gid);
$estCreateur = (int)$groupe['createur_id'] === (int)$u['id'];

/* ---------- Actions de gestion (membres, chefs, suppression) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $peutGerer) {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'ajouter_membre') {
        $email = Auth::normaliserEmail((string)($_POST['email'] ?? ''));
        // RG3 : on n'ajoute que des comptes existants ET actifs
        $cible = $pdo->prepare('SELECT id, actif FROM utilisateurs WHERE email = ?');
        $cible->execute([$email]);
        $cibleU = $cible->fetch();
        if (!$cibleU) {
            Security::flash('erreur', 'Aucun compte actif avec cet e-mail (les comptes sont créés par l\'administrateur).');
        } elseif ((int)$cibleU['actif'] !== 1) {
            Security::flash('erreur', 'Ce compte est désactivé : impossible de l\'ajouter (RG3).');
        } else {
            // unicité d'une appartenance active vérifiée par l'application
            $deja = $pdo->prepare('SELECT 1 FROM membres_groupe WHERE groupe_id=? AND utilisateur_id=? AND retire_par_id IS NULL');
            $deja->execute([$gid, $cibleU['id']]);
            if ($deja->fetchColumn()) {
                Security::flash('erreur', 'Ce membre est déjà présent dans le groupe.');
            } else {
                $pdo->prepare('INSERT INTO membres_groupe (groupe_id, utilisateur_id, est_chef, ajoute_par_id)
                               VALUES (?,?,0,?)')->execute([$gid, $cibleU['id'], $u['id']]);
                Journal::log((int)$u['id'], Journal::MEMBRE_AJOUTE, 'groupe', $gid, $gid, 'Membre #' . $cibleU['id'] . ' ajouté');
                Security::flash('succes', 'Membre ajouté au groupe.');
            }
        }
        Security::redirect(BASE_URL . '/groupe_detail.php?id=' . $gid);
    }

    if ($action === 'retirer_membre') {
        $mid = (int)($_POST['membre_id'] ?? 0);
        // Recharge l'état du membre dans la même transaction que le RG12 éventuel
        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare('UPDATE membres_groupe SET retire_par_id=?, date_retrait=NOW()
                                  WHERE groupe_id=? AND utilisateur_id=? AND retire_par_id IS NULL');
            $upd->execute([$u['id'], $gid, $mid]);
            $pdo->commit();
            Journal::log((int)$u['id'], Journal::MEMBRE_RETIRE, 'groupe', $gid, $gid, 'Membre #' . $mid . ' retiré');
            archiverSiSansChef($gid); // RG12 : archive si plus aucun chef actif
            Security::flash('succes', 'Le membre a été retiré du groupe (son compte reste actif).');
        } catch (Throwable $ex) {
            $pdo->rollBack();
            error_log('Retrait membre : ' . $ex->getMessage());
            Security::flash('erreur', 'Erreur lors du retrait.');
        }
        Security::redirect(BASE_URL . '/groupe_detail.php?id=' . $gid);
    }

    if ($action === 'nommer_chef' || $action === 'retirer_chef') {
        $mid = (int)($_POST['membre_id'] ?? 0);
        $valeur = $action === 'nommer_chef' ? 1 : 0;
        // Seul le créateur (ou l'admin) nomme / retire la qualité de chef (RG5)
        if ($action === 'retirer_chef' && !$estCreateur && $u['role_code'] !== 'admin') {
            Security::flash('erreur', 'Seul le créateur ou l\'administrateur peut retirer la qualité de chef.');
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE membres_groupe SET est_chef=? WHERE groupe_id=? AND utilisateur_id=? AND retire_par_id IS NULL')
                    ->execute([$valeur, $gid, $mid]);
                $pdo->commit();
                Journal::log((int)$u['id'], $valeur ? Journal::CHEF_NOMME : Journal::CHEF_RETIRE, 'groupe', $gid, $gid, 'Membre #' . $mid);
                archiverSiSansChef($gid); // RG12 : la perte du dernier chef archive le groupe
                Security::flash('succes', $valeur ? 'Chef nommé.' : 'Qualité de chef retirée.');
            } catch (Throwable $ex) {
                $pdo->rollBack();
                error_log('Chef : ' . $ex->getMessage());
                Security::flash('erreur', 'Erreur lors du changement.');
            }
        }
        Security::redirect(BASE_URL . '/groupe_detail.php?id=' . $gid);
    }

    if ($action === 'supprimer_groupe' && ($estCreateur || $u['role_code'] === 'admin')) {
        $motif = $u['role_code'] === 'admin' && !$estCreateur ? 'suppression_admin' : 'suppression_createur';
        $pdo->prepare('UPDATE groupes SET archive_le = NOW(), archive_motif = ? WHERE id = ? AND archive_le IS NULL')
            ->execute([$motif, $gid]);
        Journal::log((int)$u['id'], Journal::GROUPE_ARCHIVE, 'groupe', $gid, $gid, 'Motif : ' . $motif);
        Security::flash('info', 'Groupe archivé (suppression logique, restauration possible).');
        Security::redirect(BASE_URL . '/groupes.php');
    }
}

/* ---------- Affichage : membres actifs, interventions du groupe ---------- */
$m = $pdo->prepare(
    'SELECT m.*, CONCAT(u.prenom," ",u.nom) AS membre_nom, u.email, u.actif AS compte_actif
     FROM membres_groupe m JOIN utilisateurs u ON u.id = m.utilisateur_id
     WHERE m.groupe_id = ? AND m.retire_par_id IS NULL ORDER BY m.est_chef DESC, membre_nom'
);
$m->execute([$gid]);
$membres = $m->fetchAll();

$i = $pdo->prepare(
    'SELECT i.*, CONCAT(c.prenom," ",c.nom) AS auteur FROM interventions i
     JOIN utilisateurs c ON c.id = i.cree_par_id
     WHERE i.groupe_id = ? AND i.supprime_le IS NULL ORDER BY i.created_at DESC'
);
$i->execute([$gid]);
$interventions = $i->fetchAll();

$titre = $groupe['nom'];
require dirname(__DIR__) . '/src/layout/haut.php';
?>
<div class="d-flex justify-content-between align-items-center mb-2">
  <div>
    <h1 class="h4 mb-0"><?= Security::e($groupe['nom']) ?>
      <?php if ($groupe['archive_le']): ?><span class="badge bg-dark">Archivé</span><?php endif; ?>
      <?php if (estChef($gid, (int)$u['id']) || $u['role_code'] === 'admin'): ?><span class="badge bg-warning text-dark">Gestion</span><?php endif; ?>
    </h1>
    <span class="text-muted small">Créé par <strong><?= Security::e($groupe['createur_nom']) ?></strong>
      le <?= Security::e($groupe['created_at']) ?>
      <?php if ($groupe['archive_le']): ?> — archivé le <?= Security::e($groupe['archive_le']) ?>
        (motif : <?= Security::e($groupe['archive_motif']) ?>)<?php endif; ?></span>
  </div>
  <?php if (($estCreateur || $u['role_code'] === 'admin') && !$groupe['archive_le']): ?>
  <form method="post" onsubmit="return confirm('Archiver ce groupe ? Les données sont conservées ; l\'administrateur pourra le restaurer.')">
    <?= Security::csrfField() ?>
    <input type="hidden" name="groupe_id" value="<?= $gid ?>">
    <input type="hidden" name="action" value="supprimer_groupe">
    <button class="btn btn-outline-danger btn-sm">Archiver le groupe</button>
  </form>
  <?php endif; ?>
</div>

<div class="row g-3">
  <div class="col-md-5">
    <div class="card p-4 mb-3">
      <h2 class="h6 mb-3">Membres (<?= count($membres) ?>)</h2>
      <?php foreach ($membres as $membre): ?>
        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
          <div class="small">
            <strong><?= Security::e($membre['membre_nom']) ?></strong>
            <?php if ((int)$membre['est_chef'] === 1): ?><span class="badge bg-warning text-dark">chef</span><?php endif; ?>
            <?php if ((int)$membre['compte_actif'] !== 1): ?><span class="badge bg-dark">compte désactivé</span><?php endif; ?>
            <div class="text-muted"><?= Security::e($membre['email']) ?></div>
            <div class="text-muted">ajouté le <?= Security::e($membre['date_ajout']) ?></div>
          </div>
          <?php if ($peutGerer && !$groupe['archive_le']): ?>
          <div class="d-flex gap-1">
            <?php if ($estCreateur || $u['role_code'] === 'admin'): ?>
              <form method="post">
                <?= Security::csrfField() ?>
                <input type="hidden" name="groupe_id" value="<?= $gid ?>">
                <input type="hidden" name="membre_id" value="<?= (int)$membre['utilisateur_id'] ?>">
                <input type="hidden" name="action" value="<?= (int)$membre['est_chef'] ? 'retirer_chef' : 'nommer_chef' ?>">
                <button class="btn btn-sm btn-outline-<?= (int)$membre['est_chef'] ? 'danger' : 'warning' ?>">
                  <?= (int)$membre['est_chef'] ? 'Retirer chef' : 'Nommer chef' ?></button>
              </form>
            <?php endif; ?>
            <form method="post">
              <?= Security::csrfField() ?>
              <input type="hidden" name="groupe_id" value="<?= $gid ?>">
              <input type="hidden" name="membre_id" value="<?= (int)$membre['utilisateur_id'] ?>">
              <input type="hidden" name="action" value="retirer_membre">
              <button class="btn btn-sm btn-outline-danger" title="Retirer du groupe (le compte reste actif)">Retirer</button>
            </form>
          </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <?php if ($peutGerer && !$groupe['archive_le']): ?>
      <form method="post" class="mt-3">
        <?= Security::csrfField() ?>
        <input type="hidden" name="groupe_id" value="<?= $gid ?>">
        <input type="hidden" name="action" value="ajouter_membre">
        <label class="form-label small">Ajouter un membre actif par e-mail</label>
        <div class="input-group">
          <input type="email" name="email" class="form-control" placeholder="e-mail du compte" required>
          <button class="btn btn-marine">Ajouter</button>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-md-7">
    <div class="card p-4 mb-3">
      <div class="d-flex justify-content-between mb-2">
        <h2 class="h6 mb-0">Interventions du groupe (<?= count($interventions) ?>)</h2>
        <a class="btn btn-sm btn-marine" href="<?= BASE_URL ?>/intervention_nouveau.php">+ Nouvelle</a>
      </div>
      <?php if (!$interventions): ?><p class="text-muted small mb-0">Aucune intervention dans ce groupe.</p><?php endif; ?>
      <?php foreach ($interventions as $iv): ?>
        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
          <div class="small">
            <strong><?= Security::e($iv['reference']) ?></strong>
            <span class="badge bg-<?= CLASSE_STATUT[$iv['statut']] ?>"><?= Security::e(LIBELLES_STATUT[$iv['statut']]) ?></span>
            <div class="text-muted">créée par <?= Security::e($iv['auteur']) ?> le <?= Security::e($iv['created_at']) ?></div>
          </div>
          <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/intervention_detail.php?id=<?= (int)$iv['id'] ?>">Détail</a>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if (estChef($gid, (int)$u['id']) || $u['role_code'] === 'admin'): ?>
    <div class="card p-4">
      <h2 class="h6">Journal du groupe (chefs)</h2>
      <?php
      $j = $pdo->prepare(
        'SELECT j.*, CONCAT(p.prenom," ",p.nom) AS auteur FROM journal_activites j
         LEFT JOIN utilisateurs p ON p.id = j.utilisateur_id
         WHERE j.groupe_id = ? ORDER BY j.created_at DESC LIMIT 50'
      );
      $j->execute([$gid]);
      $journal = $j->fetchAll();
      ?>
      <?php if (!$journal): ?><p class="text-muted small mb-0">Aucun événement.</p><?php endif; ?>
      <?php foreach ($journal as $ev): ?>
        <div class="small border-bottom py-1">
          <span class="badge bg-light text-dark"><?= Security::e($ev['created_at']) ?></span>
          <strong><?= Security::e($ev['action']) ?></strong> — <?= Security::e($ev['details'] ?: '') ?>
          <span class="text-muted">(par <?= Security::e($ev['auteur'] ?: 'système') ?>)</span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require dirname(__DIR__) . '/src/layout/bas.php'; ?>

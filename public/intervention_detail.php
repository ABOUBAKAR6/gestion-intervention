<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/init.php';
$u = Security::exigerConnexion();
$pdo = Database::pdo();

// Charge l'intervention (jamais supprimée logiquement)
$q = $pdo->prepare(
    'SELECT i.*, s.nom AS service_nom, t.nom AS type_nom,
            cu.prenom AS c_prenom, cu.nom AS c_nom, mu.prenom AS m_prenom, mu.nom AS m_nom,
            CONCAT(te.prenom," ",te.nom) AS technicien_nom
     FROM interventions i
     LEFT JOIN services_demandeurs s ON s.id = i.service_demandeur_id
     LEFT JOIN types_intervention t  ON t.id = i.type_intervention_id
     LEFT JOIN utilisateurs cu ON cu.id = i.cree_par_id
     LEFT JOIN utilisateurs mu ON mu.id = i.modifie_par_id
     LEFT JOIN utilisateurs te ON te.id = i.technicien_id
     WHERE i.id = ? AND i.supprime_le IS NULL'
);
$q->execute([(int)($_GET['id'] ?? 0)]);
$int = $q->fetch();
if (!$int || !peutAccederIntervention($u, $int)) {
    Journal::log((int)$u['id'], 'acces_refuse', 'intervention', (int)($_GET['id'] ?? 0));
    http_response_code(403);
    $titre = 'Accès refusé';
    require dirname(__DIR__) . '/src/layout/haut.php';
    echo '<div class="alert alert-danger">Accès refusé : intervention inexistante ou hors de votre périmètre.</div>';
    require dirname(__DIR__) . '/src/layout/bas.php';
    exit;
}

Security::csrfVerifier();

/* ---------- Changement de statut ---------- */
if (($_POST['action'] ?? '') === 'statut') {
    $nouveau = (string)($_POST['statut'] ?? '');
    $commentaire = trim((string)($_POST['commentaire'] ?? ''));
    if (isset(LIBELLES_STATUT[$nouveau]) && $nouveau !== $int['statut']) {
        if (in_array($nouveau, ['resolue', 'non_resolue'], true)
            && ($commentaire === '' || !empty($_POST['date_resolution']) === false)) {
            // Les statuts de clôture exigent un commentaire (date prise à l'instant)
            Security::flash('erreur', 'Un commentaire de résolution est obligatoire pour clôturer.');
        } else {
            if (in_array($nouveau, ['resolue', 'non_resolue'], true)) {
                $pdo->prepare('UPDATE interventions SET statut=?, date_prise_en_charge=COALESCE(date_prise_en_charge, NOW()),
                               date_resolution=NOW(), commentaire_resolution=?, modifie_par_id=? WHERE id=?')
                    ->execute([$nouveau, $commentaire, $u['id'], $int['id']]);
            } else {
                $pdo->prepare('UPDATE interventions SET statut=?, date_prise_en_charge=COALESCE(date_prise_en_charge, NOW()),
                               modifie_par_id=? WHERE id=?')
                    ->execute([$nouveau, $u['id'], $int['id']]);
            }
            $pdo->prepare('INSERT INTO historique_statuts (intervention_id, ancien_statut, nouveau_statut, change_par_id, commentaire)
                           VALUES (?,?,?,?,?)')
                ->execute([$int['id'], $int['statut'], $nouveau, $u['id'], $commentaire !== '' ? $commentaire : null]);
            Journal::log((int)$u['id'], Journal::INTERV_STATUT, 'intervention', (int)$int['id'],
                $int['groupe_id'], LIBELLES_STATUT[$int['statut']] . ' → ' . LIBELLES_STATUT[$nouveau]);
            Security::flash('succes', 'Statut mis à jour : ' . LIBELLES_STATUT[$nouveau] . '.');
        }
    }
    Security::redirect(BASE_URL . '/intervention_detail.php?id=' . (int)$int['id']);
}

/* ---------- Ajout d'un besoin en matériel ---------- */
if (($_POST['action'] ?? '') === 'ajout_besoin') {
    $desc = trim((string)($_POST['description_besoin'] ?? ''));
    $qt   = max(1, (int)($_POST['quantite'] ?? 1));
    if ($desc !== '') {
        $pdo->prepare('INSERT INTO besoins_materiel (intervention_id, description_besoin, quantite, signale_par_id)
                       VALUES (?,?,?,?)')
            ->execute([$int['id'], $desc, $qt, $u['id']]);
        Journal::log((int)$u['id'], Journal::BESOIN_AJOUTE, 'intervention', (int)$int['id'],
            $int['groupe_id'], $desc . ' x' . $qt);
        Security::flash('succes', 'Besoin en matériel ajouté.');
    }
    Security::redirect(BASE_URL . '/intervention_detail.php?id=' . (int)$int['id']);
}

/* ---------- Cycle d'un besoin ---------- */
if (($_POST['action'] ?? '') === 'besoin_statut') {
    $besoinId = (int)($_POST['besoin_id'] ?? 0);
    $cycle    = (string)($_POST['cycle'] ?? '');
    if (isset(LIBELLES_BESOIN[$cycle])) {
        $pdo->prepare('UPDATE besoins_materiel SET statut=?, modifie_par_id=?, updated_at=NOW()
                       WHERE id=? AND intervention_id=?')
            ->execute([$cycle, $u['id'], $besoinId, $int['id']]);
        Journal::log((int)$u['id'], Journal::BESOIN_STATUT, 'intervention', (int)$int['id'],
            $int['groupe_id'], 'Besoin #' . $besoinId . ' → ' . LIBELLES_BESOIN[$cycle]);
    }
    Security::redirect(BASE_URL . '/intervention_detail.php?id=' . (int)$int['id']);
}

/* ---------- Suppression logique ---------- */
if (($_POST['action'] ?? '') === 'supprimer') {
    // auteur de la création ou administrateur
    if ((int)$int['cree_par_id'] === (int)$u['id'] || $u['role_code'] === 'admin') {
        $pdo->prepare('UPDATE interventions SET supprime_par_id=?, supprime_le=NOW() WHERE id=?')
            ->execute([$u['id'], $int['id']]);
        Journal::log((int)$u['id'], Journal::INTERV_SUPPRIMEE, 'intervention', (int)$int['id'],
            $int['groupe_id'], 'Réf. ' . $int['reference'] . ' (suppression logique)');
        Security::flash('info', 'Intervention supprimée (logiquement) : ' . $int['reference']);
        Security::redirect(BASE_URL . '/interventions.php');
    } else {
        http_response_code(403);
        Security::flash('erreur', 'Seul l\'auteur ou l\'administrateur peut supprimer.');
        Security::redirect(BASE_URL . '/intervention_detail.php?id=' . (int)$int['id']);
    }
}

/* ---------- Affichage ---------- */
$hist = $pdo->prepare(
    'SELECT h.*, CONCAT(p.prenom," ",p.nom) AS auteur FROM historique_statuts h
     JOIN utilisateurs p ON p.id = h.change_par_id
     WHERE h.intervention_id = ? ORDER BY h.change_le DESC'
);
$hist->execute([$int['id']]);
$historique = $hist->fetchAll();

$besoins = $pdo->prepare(
    'SELECT b.*, CONCAT(p.prenom," ",p.nom) AS signaleur, CONCAT(m.prenom," ",m.nom) AS modifieur
     FROM besoins_materiel b
     JOIN utilisateurs p ON p.id = b.signale_par_id
     LEFT JOIN utilisateurs m ON m.id = b.modifie_par_id
     WHERE b.intervention_id = ? ORDER BY b.id DESC'
);
$besoins->execute([$int['id']]);
$listeBesoins = $besoins->fetchAll();

$titre = $int['reference'];
require dirname(__DIR__) . '/src/layout/haut.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h4 mb-0"><?= Security::e($int['reference']) ?>
      <span class="badge bg-<?= CLASSE_STATUT[$int['statut']] ?>"><?= Security::e(LIBELLES_STATUT[$int['statut']]) ?></span>
    </h1>
    <span class="text-muted small">
      Espace : <?= $int['groupe_id'] ? 'Groupe' : 'Personnel' ?> — créée par
      <strong><?= Security::e($int['c_prenom'] . ' ' . $int['c_nom']) ?></strong>
      le <?= Security::e($int['created_at']) ?>
      <?php if ($int['modifie_par_id']): ?>
        — dernière modification par <strong><?= Security::e($int['m_prenom'] . ' ' . $int['m_nom']) ?></strong>
      <?php endif; ?>
    </span>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-primary btn-sm" href="<?= BASE_URL ?>/pdf_fiche.php?id=<?= (int)$int['id'] ?>">📄 Fiche PDF</a>
    <?php if ((int)$int['cree_par_id'] === (int)$u['id'] || $u['role_code'] === 'admin'): ?>
    <form method="post" onsubmit="return confirm('Supprimer logiquement cette intervention ?')">
      <?= Security::csrfField() ?>
      <input type="hidden" name="action" value="supprimer">
      <button class="btn btn-outline-danger btn-sm">Supprimer</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-7">
    <div class="card p-4 mb-3">
      <table class="table table-sm mb-0">
        <tr><th class="text-muted" style="width:180px">Service demandeur</th>
            <td><?= Security::e($int['service_nom'] ?: $int['service_autre']) ?></td></tr>
        <tr><th class="text-muted">Branche technique</th>
            <td><?= Security::e($int['type_nom'] ?: $int['type_autre']) ?></td></tr>
        <tr><th class="text-muted">Demandeur</th>
            <td><?= Security::e($int['nom_demandeur']) ?> — <?= Security::e($int['telephone_demandeur']) ?></td></tr>
        <tr><th class="text-muted">Priorité</th>
            <td><?= Security::e(LIBELLES_PRIORITE[$int['priorite']]) ?></td></tr>
        <tr><th class="text-muted">Technicien</th>
            <td><?= Security::e($int['technicien_nom'] ?: '—') ?></td></tr>
        <tr><th class="text-muted">Signalement</th>
            <td><?= Security::e($int['date_signalement']) ?></td></tr>
        <tr><th class="text-muted">Prise en charge</th>
            <td><?= Security::e($int['date_prise_en_charge'] ?: '—') ?></td></tr>
        <tr><th class="text-muted">Résolution</th>
            <td><?= Security::e($int['date_resolution'] ?: '—') ?></td></tr>
        <?php if ($int['commentaire_resolution']): ?>
        <tr><th class="text-muted">Commentaire</th><td><?= Security::e($int['commentaire_resolution']) ?></td></tr>
        <?php endif; ?>
      </table>
      <hr>
      <h2 class="h6 text-muted">Description du problème</h2>
      <p class="mb-0" style="white-space:pre-line"><?= Security::e($int['description_probleme']) ?></p>
    </div>
  </div>

  <div class="col-md-5">
    <div class="card p-4 mb-3">
      <h2 class="h6 mb-3">Changer le statut</h2>
      <form method="post">
        <?= Security::csrfField() ?>
        <input type="hidden" name="action" value="statut">
        <div class="mb-2"><select name="statut" class="form-select">
          <?php foreach (LIBELLES_STATUT as $c => $l): ?>
            <option value="<?= $c ?>" <?= $int['statut'] === $c ? 'selected' : '' ?>><?= Security::e($l) ?></option>
          <?php endforeach; ?>
        </select></div>
        <div class="mb-2"><input type="text" name="commentaire" class="form-control"
               placeholder="Commentaire (obligatoire si résolue / non résolue)"></div>
        <button class="btn btn-marine btn-sm">Mettre à jour</button>
      </form>
    </div>

    <div class="card p-4 mb-3">
      <h2 class="h6">Historique des statuts (traçabilité)</h2>
      <ul class="list-unstyled mb-0 small">
      <?php foreach ($historique as $h): ?>
        <li class="mb-1">
          <span class="badge bg-light text-dark"><?= Security::e($h['change_le']) ?></span>
          <?= Security::e($h['ancien_statut'] ? LIBELLES_STATUT[$h['ancien_statut']] : '—') ?>
          → <strong><?= Security::e(LIBELLES_STATUT[$h['nouveau_statut']]) ?></strong>
          par <?= Security::e($h['auteur']) ?>
          <?php if ($h['commentaire']): ?><div class="text-muted ms-2">« <?= Security::e($h['commentaire']) ?> »</div><?php endif; ?>
        </li>
      <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <div class="col-12">
    <div class="card p-4">
      <h2 class="h6 mb-3">Besoins en matériel (cycle : signalé → commandé → reçu)</h2>
      <?php if (!$listeBesoins): ?><p class="text-muted small mb-2">Aucun besoin enregistré.</p><?php endif; ?>
      <?php foreach ($listeBesoins as $b): ?>
        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
          <div>
            <strong><?= Security::e($b['description_besoin']) ?></strong> × <?= (int)$b['quantite'] ?>
            <span class="badge bg-<?= $b['statut'] === 'recu' ? 'success' : ($b['statut'] === 'commande' ? 'warning text-dark' : 'secondary') ?>">
              <?= Security::e(LIBELLES_BESOIN[$b['statut']]) ?></span>
            <div class="text-muted small">signalé par <?= Security::e($b['signaleur']) ?>, le <?= Security::e($b['date_signalement']) ?>
              <?php if ($b['modifieur']): ?> — modifié par <?= Security::e($b['modifieur']) ?> le <?= Security::e($b['updated_at']) ?><?php endif; ?></div>
          </div>
          <div class="d-flex gap-1">
            <?php foreach (LIBELLES_BESOIN as $c => $l): ?>
              <form method="post">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="besoin_statut">
                <input type="hidden" name="besoin_id" value="<?= (int)$b['id'] ?>">
                <input type="hidden" name="cycle" value="<?= $c ?>">
                <button class="btn btn-sm btn-outline-secondary <?= $b['statut'] === $c ? 'active' : '' ?>"><?= Security::e($l) ?></button>
              </form>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <form method="post" class="row g-2 mt-3" >
        <?= Security::csrfField() ?>
        <input type="hidden" name="action" value="ajout_besoin">
        <div class="col-md-7"><input type="text" name="description_besoin" class="form-control" placeholder="Matériel manquant (texte libre)" required></div>
        <div class="col-md-2"><input type="number" name="quantite" class="form-control" value="1" min="1"></div>
        <div class="col-md-3"><button class="btn btn-outline-primary w-100">Ajouter le besoin</button></div>
      </form>
    </div>
  </div>
</div>
<?php require dirname(__DIR__) . '/src/layout/bas.php'; ?>

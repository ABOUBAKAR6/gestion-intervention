<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/src/init.php';
$u = Security::exigerAdmin();
$pdo = Database::pdo();
Security::csrfVerifier();

/* ---------- Création d'un compte (RG2 : seul l'admin crée les comptes) ---------- */
if (($_POST['action'] ?? '') === 'creer') {
    $email = Auth::normaliserEmail((string)($_POST['email'] ?? ''));
    $nom   = trim((string)($_POST['nom'] ?? ''));
    $prenom= trim((string)($_POST['prenom'] ?? ''));
    $tel   = trim((string)($_POST['telephone'] ?? ''));
    $role  = $_POST['role'] === 'admin' ? 'admin' : 'utilisateur';
    $ok    = true;
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { Security::flash('erreur', 'E-mail invalide.'); $ok = false; }
    if ($email !== '') {
        $e = $pdo->prepare('SELECT 1 FROM utilisateurs WHERE email = ?'); $e->execute([$email]);
        if ($e->fetchColumn()) { Security::flash('erreur', 'Cet e-mail est déjà utilisé.'); $ok = false; }
    }
    if ($ok) {
        $temp  = Auth::motDePasseTemporaire(); // mot de passe temporaire robuste, haché uniquement
        $roleId= $pdo->prepare('SELECT id FROM roles WHERE code = ?'); $roleId->execute([$role]);
        $roleId= (int)$roleId->fetchColumn();
        $pdo->prepare(
            'INSERT INTO utilisateurs (role_id, nom, prenom, email, telephone, password_hash, actif, doit_changer_mot_de_passe)
             VALUES (?, ?, ?, ?, ?, ?, 1, 1)'
        )->execute([$roleId, $nom, $prenom, $email, $tel !== '' ? $tel : null, password_hash($temp, PASSWORD_DEFAULT)]);
        $cid = (int)$pdo->lastInsertId();
        Journal::log((int)$u['id'], Journal::COMPTES_CRUES, 'utilisateur', $cid, null, 'Rôle : ' . $role);
        Security::flash('avertissement', 'Compte créé. Mot de passe temporaire (à communiquer une fois) : ' . $temp);
    }
    Security::redirect(BASE_URL . '/admin/comptes.php');
}

/* ---------- Désactivation / réactivation ---------- */
if (($_POST['action'] ?? '') === 'desactiver') {
    $cid = (int)($_POST['utilisateur_id'] ?? 0);
    if ($cid !== (int)$u['id']) {
        // Fin de session : auth_version++ invalide les sessions ouvertes (prochaine requête refusée)
        $pdo->prepare('UPDATE utilisateurs SET actif = 0, auth_version = auth_version + 1, updated_at = NOW() WHERE id = ?')
            ->execute([$cid]);
        Journal::log((int)$u['id'], Journal::COMPTE_DESACTIVE, 'utilisateur', $cid);
        archiverGroupesDe($cid); // RG12 : ex-chef désactivé => possible archivage
        Security::flash('succes', 'Compte désactivé. Sessions invalidées immédiatement.');
    } else {
        Security::flash('erreur', 'Impossible de désactiver votre propre compte d\'administration.');
    }
    Security::redirect(BASE_URL . '/admin/comptes.php');
}
if (($_POST['action'] ?? '') === 'reactiver') {
    $cid = (int)($_POST['utilisateur_id'] ?? 0);
    $pdo->prepare('UPDATE utilisateurs SET actif = 1, doit_changer_mot_de_passe = 1, updated_at = NOW() WHERE id = ?')
        ->execute([$cid]);
    Journal::log((int)$u['id'], Journal::COMPTE_REACTIVER, 'utilisateur', $cid);
    Security::flash('succes', 'Compte réactivé : un nouveau mot de passe temporaire est requis.');
    Security::redirect(BASE_URL . '/admin/comptes.php');
}

/* ---------- Réinitialisation : mot de passe temporaire ---------- */
if (($_POST['action'] ?? '') === 'reset_mdp') {
    $cid = (int)($_POST['utilisateur_id'] ?? 0);
    $temp = Auth::motDePasseTemporaire();
    $pdo->prepare('UPDATE utilisateurs SET password_hash = ?, doit_changer_mot_de_passe = 1,
                   auth_version = auth_version + 1, updated_at = NOW() WHERE id = ?')
        ->execute([password_hash($temp, PASSWORD_DEFAULT), $cid]);
    Journal::log((int)$u['id'], Journal::COMPTE_MDP_RESET, 'utilisateur', $cid);
    Security::flash('avertissement', 'Mot de passe temporaire (à communiquer une fois) : ' . $temp);
    Security::redirect(BASE_URL . '/admin/comptes.php');
}

$comptes = $pdo->query(
    'SELECT u.*, r.nom AS role_nom FROM utilisateurs u JOIN roles r ON r.id = u.role_id ORDER BY u.nom, u.prenom'
)->fetchAll();

$titre = 'Comptes';
require dirname(__DIR__, 2) . '/src/layout/haut.php';
?>
<h1 class="h4 mb-3">Gestion des comptes</h1>

<div class="card p-4 mb-4">
  <h2 class="h6 mb-3">Créer un compte</h2>
  <form method="post" class="row g-2">
    <?= Security::csrfField() ?>
    <input type="hidden" name="action" value="creer">
    <div class="col-md-3"><input type="email" name="email" class="form-control" placeholder="E-mail (identifiant)" required></div>
    <div class="col-md-2"><input type="text" name="prenom" class="form-control" placeholder="Prénom"></div>
    <div class="col-md-2"><input type="text" name="nom" class="form-control" placeholder="Nom"></div>
    <div class="col-md-2"><input type="text" name="telephone" class="form-control" placeholder="Téléphone (non obligatoire)"></div>
    <div class="col-md-2"><select name="role" class="form-select">
        <option value="utilisateur">Utilisateur</option><option value="admin">Administrateur</option></select></div>
    <div class="col-md-1"><button class="btn btn-marine w-100">OK</button></div>
  </form>
  <p class="small text-muted mt-2 mb-0">Un mot de passe temporaire alors généré : changement obligatoire à la première connexion.</p>
</div>

<div class="card p-0">
<table class="table mb-0 align-middle">
<thead><tr><th>Nom</th><th>Rôle</th><th>État</th><th>Dernière connexion</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($comptes as $cpt): ?>
<tr>
  <td class="small"><strong><?= Security::e($cpt['prenom'] . ' ' . $cpt['nom']) ?></strong><br><?= Security::e($cpt['email']) ?></td>
  <td class="small"><?= Security::e($cpt['role_nom']) ?></td>
  <td class="small">
    <?php if ((int)$cpt['actif'] === 1): ?><span class="badge bg-success">Actif</span>
    <?php else: ?><span class="badge bg-dark">Désactivé</span><?php endif; ?>
    <?php if ((int)$cpt['doit_changer_mot_de_passe'] === 1): ?><span class="badge bg-warning text-dark">Mdp à changer</span><?php endif; ?>
  </td>
  <td class="small"><?= Security::e($cpt['last_login_at'] ?: '—') ?></td>
  <td class="text-nowrap">
    <?php if ((int)$cpt['id'] !== (int)$u['id']): ?>
      <form method="post" class="d-inline">
        <?= Security::csrfField() ?>
        <input type="hidden" name="utilisateur_id" value="<?= (int)$cpt['id'] ?>">
      <input type="hidden" name="action" value="<?= (int)$cpt['actif'] === 1 ? 'desactiver' : 'reactiver' ?>">
        <button class="btn btn-sm btn-outline-<?= (int)$cpt['actif'] === 1 ? 'danger' : 'success' ?>">
          <?= (int)$cpt['actif'] === 1 ? 'Désactiver' : 'Réactiver' ?></button>
      </form>
      <?php if ((int)$cpt['actif'] === 1): ?>
      <form method="post" class="d-inline" onsubmit="return confirm('Générer un mot de passe temporaire ?')">
        <?= Security::csrfField() ?>
        <input type="hidden" name="utilisateur_id" value="<?= (int)$cpt['id'] ?>">
        <input type="hidden" name="action" value="reset_mdp">
        <button class="btn btn-sm btn-outline-warning">Reset MDP</button>
      </form>
      <?php endif; ?>
    <?php else: ?>
      <span class="text-muted small">(vous)</span>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/bas.php'; ?>

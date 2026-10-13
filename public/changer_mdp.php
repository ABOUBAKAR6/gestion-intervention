<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/init.php';
Security::demarrerSession();
$u = Security::utilisateurCourant();
if ($u === null) {
    Security::redirect(BASE_URL . '/login.php');
}
$obligatoire = (int)$u['doit_changer_mot_de_passe'] === 1;
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::csrfVerifier();
    $actuel    = (string)($_POST['actuel'] ?? '');
    $nouveau   = (string)($_POST['nouveau'] ?? '');
    $confirme  = (string)($_POST['confirme'] ?? '');

    if (!password_verify($actuel, $u['password_hash'])) {
        $erreur = 'Mot de passe actuel incorrect.';
    } elseif (mb_strlen($nouveau) < MOT_DE_PASSE_MIN) {
        $erreur = 'Le nouveau mot de passe doit compter au moins ' . MOT_DE_PASSE_MIN . ' caractères.';
    } elseif ($nouveau !== $confirme) {
        $erreur = 'La confirmation ne correspond pas.';
    } elseif ($nouveau === $actuel) {
        $erreur = 'Le nouveau mot de passe doit être différent.';
    } else {
        Auth::changerMotDePasse((int)$u['id'], $nouveau);
        Security::flash('succes', 'Mot de passe modifié. Sessions précédentes invalidées.');
        Security::redirect(BASE_URL . '/tableau_bord.php');
    }
}

$titre = 'Changer mon mot de passe';
require dirname(__DIR__) . '/src/layout/haut.php';
?>
<div class="row justify-content-center"><div class="col-md-6">
  <div class="card p-4">
    <h2 class="h5 mb-3"><?= $obligatoire ? 'Changement obligatoire du mot de passe' : 'Changer mon mot de passe' ?></h2>
    <?php if ($obligatoire): ?>
      <div class="alert alert-warning py-2">Un mot de passe temporaire vous a été attribué. Vous devez en choisir un nouveau avant d'accéder à l'application.</div>
    <?php endif; ?>
    <?php if ($erreur): ?><div class="alert alert-danger py-2"><?= Security::e($erreur) ?></div><?php endif; ?>
    <form method="post">
      <?= Security::csrfField() ?>
      <div class="mb-3"><label class="form-label">Mot de passe actuel</label>
        <input type="password" name="actuel" class="form-control" required></div>
      <div class="mb-3"><label class="form-label">Nouveau mot de passe (min. <?= MOT_DE_PASSE_MIN ?> caractères)</label>
        <input type="password" name="nouveau" class="form-control" required minlength="<?= MOT_DE_PASSE_MIN ?>"></div>
      <div class="mb-3"><label class="form-label">Confirmation</label>
        <input type="password" name="confirme" class="form-control" required></div>
      <button class="btn btn-marine">Enregistrer</button>
    </form>
  </div>
</div></div>
<?php require dirname(__DIR__) . '/src/layout/bas.php'; ?>

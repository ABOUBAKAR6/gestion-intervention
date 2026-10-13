<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/init.php';
Security::demarrerSession();

if (Security::utilisateurCourant() !== null) {
    Security::redirect(BASE_URL . '/tableau_bord.php');
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::csrfVerifier();
    $email = trim((string)($_POST['email'] ?? ''));
    $mdp   = (string)($_POST['mot_de_passe'] ?? '');
    $ip    = $_SERVER['REMOTE_ADDR'] ?? '';
    $u = Auth::connecter($email, $mdp, $ip);
    if ($u !== null) {
        Security::redirect(BASE_URL . '/tableau_bord.php');
    }
    // Message UNIQUEMENT générique (ne révèle pas si le compte existe)
    $message = 'Identifiants invalides, compte inaccessible ou trop de tentatives. Réessayez plus tard.';
}
$expire = isset($_GET['expire']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Connexion — <?= Security::e(APP_NOM) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
*{font-family:'Inter',system-ui,sans-serif; box-sizing:border-box;}
body{margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
  color:#1c2333;
  background:
    radial-gradient(1100px 500px at 85% -10%, rgba(212,175,55,.16), transparent 60%),
    radial-gradient(900px 480px at -10% 110%, rgba(212,175,55,.10), transparent 55%),
    linear-gradient(135deg,#0f1e3d 0%,#1b2a4a 55%,#24375e 100%);}
.carte{width:100%; max-width:430px; background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.14);
  border-radius:20px; padding:2.6rem 2.4rem; backdrop-filter:blur(14px); -webkit-backdrop-filter:blur(14px);
  box-shadow:0 24px 60px rgba(4,10,26,.45);}
.logo{width:64px;height:64px;border-radius:16px;display:flex;align-items:center;justify-content:center;
  background:linear-gradient(135deg,#d4af37,#b8912c); color:#0f1e3d; font-size:1.7rem;
  box-shadow:0 10px 24px rgba(212,175,55,.35); margin:0 auto 1.1rem;}
h1{color:#fff; font-size:1.35rem; font-weight:700; margin:.2rem 0 .3rem; text-align:center;}
.sous{text-align:center; color:rgba(255,255,255,.62); font-size:.86rem; margin-bottom:1.6rem;}
.sous .or{color:#d4af37; font-weight:600;}
label{display:block; color:rgba(255,255,255,.78); font-size:.8rem; font-weight:600;
  letter-spacing:.5px; text-transform:uppercase; margin-bottom:.4rem;}
input{width:100%; padding:.85rem 1rem; border-radius:12px; border:1px solid rgba(255,255,255,.18);
  background:rgba(255,255,255,.06); color:#fff; font-size:.95rem; outline:none; transition:border .15s, background .15s;}
input::placeholder{color:rgba(255,255,255,.35);}
input:focus{border-color:#d4af37; background:rgba(255,255,255,.10); box-shadow:0 0 0 3px rgba(212,175,55,.18);}
.bouton{width:100%; padding:.9rem; border:0; border-radius:12px; cursor:pointer;
  background:linear-gradient(135deg,#d4af37,#b8912c); color:#0f1e3d; font-weight:700; font-size:.95rem;
  letter-spacing:.4px; transition:transform .15s, box-shadow .15s; box-shadow:0 10px 24px rgba(212,175,55,.30);}
.bouton:hover{transform:translateY(-1px); box-shadow:0 14px 30px rgba(212,175,55,.42);}
.alerte{border-radius:12px; padding:.7rem .9rem; font-size:.85rem; margin-bottom:1rem;}
.alerte-err{background:rgba(220,53,69,.16); border:1px solid rgba(220,53,69,.4); color:#ffb6bd;}
.alerte-info{background:rgba(255,193,7,.14); border:1px solid rgba(255,193,7,.35); color:#ffe69c;}
.note{text-align:center; color:rgba(255,255,255,.45); font-size:.78rem; margin-top:1.4rem; line-height:1.5;}
.pied{position:fixed; bottom:14px; left:0; right:0; text-align:center; color:rgba(255,255,255,.28); font-size:.72rem; letter-spacing:.6px;}
</style>
</head>
<body>
<div class="carte">
  <div class="logo"><i class="bi bi-tools"></i></div>
  <h1>Gestion des interventions</h1>
  <p class="sous">Service technique du CHU — <span class="or">accès réservé</span></p>

  <?php if ($expire): ?><div class="alerte alerte-info">Session expirée après inactivité. Reconnectez-vous.</div><?php endif; ?>
  <?php if ($message): ?><div class="alerte alerte-err"><i class="bi bi-shield-exclamation me-1"></i><?= Security::e($message) ?></div><?php endif; ?>

  <form method="post" novalidate>
    <?= Security::csrfField() ?>
    <div class="mb-3">
      <label>Adresse e-mail</label>
      <input type="email" name="email" required autofocus placeholder="prenom.nom@chu.com"
             value="<?= Security::e((string)($_POST['email'] ?? '')) ?>">
    </div>
    <div class="mb-4">
      <label>Mot de passe</label>
      <input type="password" name="mot_de_passe" required placeholder="••••••••••••">
    </div>
    <button class="bouton">Se connecter <i class="bi bi-arrow-right ms-1"></i></button>
    <p class="note">Aucune inscription publique : les comptes sont créés par l'administrateur.<br>
    Maximum 5 tentatives toutes les 15 minutes.</p>
  </form>
</div>
<div class="pied">SSPCT · SERVICE TECHNIQUE · TRAÇABILITÉ COMPLÈTE</div>
</body>
</html>

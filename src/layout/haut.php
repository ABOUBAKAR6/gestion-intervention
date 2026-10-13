<?php /** Gabarit haut — thème premium. Attend $titre, $u (utilisateur connecté). */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Security::e($titre ?? APP_NOM) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{
  --marine:#0f1e3d; --marine-2:#1b2a4a; --marine-3:#24375e;
  --or:#d4af37; --or-clair:#f0d98c; --or-fonce:#b8912c;
  --surface:#f6f7fb; --carte:#ffffff; --texte:#1c2333;
}
*{font-family:'Inter',system-ui,sans-serif;}
body{background:
  radial-gradient(1200px 400px at 80% -10%, rgba(212,175,55,.10), transparent 60%),
  radial-gradient(900px 380px at -10% 0%, rgba(27,42,74,.08), transparent 55%),
  var(--surface);
  color:var(--texte);}
h1,h2,h3,h4,h5,h6{font-weight:700;color:var(--marine);}
/* ---- Barre de navigation ---- */
.navbar{background:linear-gradient(120deg,var(--marine) 0%,var(--marine-2) 60%,var(--marine-3) 100%);
  border-bottom:1px solid rgba(212,175,55,.25); box-shadow:0 4px 18px rgba(15,30,61,.25);}
.navbar .nav-link{color:rgba(255,255,255,.82); font-weight:500; padding:.55rem .9rem!important; border-radius:.6rem;
  transition:all .18s ease; position:relative;}
.navbar .nav-link:hover{color:#fff; background:rgba(255,255,255,.07);}
.navbar .nav-link.active{color:var(--or-clair)!important; background:rgba(212,175,55,.12);}
.navbar-brand{font-weight:800; letter-spacing:.4px; font-size:1.12rem;}
.navbar-brand .logo-badge{display:inline-flex; align-items:center; justify-content:center;
  width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,var(--or),var(--or-fonce));
  color:var(--marine); margin-right:.55rem; font-size:.95rem;}
.navbar-brand span.or{color:var(--or-clair);}
.navbar-text{background:rgba(255,255,255,.08); border-radius:2rem; padding:.35rem .9rem; font-size:.85rem;}
/* ---- Cartes ---- */
.card{background:var(--carte); border:1px solid rgba(15,30,61,.06); border-radius:14px;
  box-shadow:0 1px 2px rgba(15,30,61,.04), 0 8px 24px rgba(15,30,61,.06);}
.card:hover{box-shadow:0 2px 4px rgba(15,30,61,.06), 0 12px 28px rgba(15,30,61,.09);}
/* ---- Boutons ---- */
.btn{border-radius:10px; font-weight:600; letter-spacing:.2px;}
.btn-marine{background:linear-gradient(135deg,var(--marine-2),var(--marine-3)); color:#fff; border:0;
  box-shadow:0 2px 8px rgba(15,30,61,.25);}
.btn-marine:hover{background:linear-gradient(135deg,var(--marine),var(--marine-2)); color:#fff; transform:translateY(-1px);
  box-shadow:0 4px 12px rgba(15,30,61,.32);}
.btn-outline-primary{--bs-btn-color:var(--marine-2); --bs-btn-border-color:var(--marine-3); --bs-btn-hover-bg:var(--marine-2);}
.btn-primary{--bs-btn-bg:var(--marine-2); --bs-btn-border-color:var(--marine-2);
  --bs-btn-hover-bg:var(--marine-3); --bs-btn-hover-border-color:var(--marine-3);}
/* ---- Badges ---- */
.badge{border-radius:20px; padding:.42em .8em; font-weight:600; letter-spacing:.3px;}
.bg-marine{background:var(--marine-2)!important; color:#fff;}
/* ---- Tableaux ---- */
.table{--bs-table-hover-bg:rgba(212,175,55,.06);}
.table thead th{color:var(--marine); font-size:.72rem; text-transform:uppercase; letter-spacing:1px;
  background:rgba(15,30,61,.035); border-bottom:1px solid rgba(15,30,61,.12)!important;}
.table td{vertical-align:middle;}
/* ---- Statistiques premium ---- */
.stat-card{border-radius:16px; overflow:hidden; position:relative; color:#fff;
  box-shadow:0 8px 24px rgba(15,30,61,.18); transition:transform .18s ease; border:0;}
.stat-card:hover{transform:translateY(-3px);}
.stat-card .valeur{font-size:2rem; font-weight:800; line-height:1;}
.stat-card .icone{position:absolute; right:14px; top:50%; transform:translateY(-50%);
  font-size:2.4rem; opacity:.22;}
.stat-marine{background:linear-gradient(135deg,#1b2a4a,#24375e);}
.stat-or{background:linear-gradient(135deg,#b8912c,#d4af37);}
.stat-vert{background:linear-gradient(135deg,#14603a,#1e8a54);}
.stat-ambre{background:linear-gradient(135deg,#8a6d1f,#c9a227);}
/* ---- Divers ---- */
.s-titre{display:flex; align-items:center; gap:.5rem; font-weight:700; color:var(--marine);}
.s-titre::after{content:""; flex:1; height:3px; border-radius:3px;
  background:linear-gradient(90deg,var(--or),transparent); }
a{color:var(--marine-3); }
a:hover{color:var(--or-fonce);}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg mb-4 py-2">
<div class="container">
  <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>/tableau_bord.php">
    <span class="logo-badge"><i class="bi bi-tools"></i></span>Interventions <span class="or">&nbsp;CHU</span></a>
  <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav">
    <span class="navbar-toggler-icon" style="filter:invert(1)"></span></button>
  <div class="collapse navbar-collapse" id="nav">
    <ul class="navbar-nav me-auto">
      <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/tableau_bord.php"><i class="bi bi-speedometer2 me-1"></i>Tableau de bord</a></li>
      <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/interventions.php"><i class="bi bi-card-checklist me-1"></i>Interventions</a></li>
      <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/groupes.php"><i class="bi bi-people me-1"></i>Groupes</a></li>
      <?php if (($u['role_code'] ?? '') === 'admin'): ?>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/accueil.php"><i class="bi bi-shield-lock me-1"></i>Administration</a></li>
      <?php endif; ?>
    </ul>
    <ul class="navbar-nav align-items-center">
      <li class="nav-item navbar-text me-2">
        <i class="bi bi-person-circle me-1"></i><?= Security::e($u['prenom'] . ' ' . $u['nom']) ?>
        <span class="small fw-semibold" style="color:#f0d98c">· <?= Security::e($u['role_nom'] ?? $u['role_code']) ?></span>
      </li>
      <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/changer_mdp.php" title="Mot de passe"><i class="bi bi-key"></i></a></li>
      <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/deconnexion.php" title="Déconnexion"><i class="bi bi-box-arrow-right"></i></a></li>
    </ul>
  </div>
</div>
</nav>
<div class="container" style="max-width:1150px">
<?= Security::flashBoite() ?>

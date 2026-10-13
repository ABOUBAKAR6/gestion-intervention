<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/init.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
$u = Security::exigerConnexion();
$pdo = Database::pdo();

/**
 * Version finale de la fiche : page d'impression navigateur
 * (Bootstrap + feuille de style d'impression), en complément du PDF Dompdf.
 * Accès : mêmes contrôles que le PDF (connecté, actif, membre ou admin).
 */
$q = $pdo->prepare(
    'SELECT i.*, s.nom AS service_nom, t.nom AS type_nom,
            CONCAT(cu.prenom," ",cu.nom) AS cree_par, CONCAT(mu.prenom," ",mu.nom) AS modifie_par,
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
    Journal::log((int)$u['id'], 'acces_refuse_impression', 'intervention', (int)($_GET['id'] ?? 0));
    http_response_code(403);
    exit('Accès refusé.');
}

$bes = $pdo->prepare('SELECT * FROM besoins_materiel WHERE intervention_id = ? ORDER BY id');
$bes->execute([$int['id']]);
$besoins = $bes->fetchAll();

Journal::log((int)$u['id'], 'impression_genere', 'intervention', (int)$int['id'], $int['groupe_id'], 'Réf. ' . $int['reference']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Fiche <?= Security::e($int['reference']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root { --bleu: #1b2a4a; --or: #c9a227; }
body { color: var(--bleu); }
.bandeau { background: var(--bleu); color: #fff; padding: 14px 18px; }
.bandeau .or { color: var(--or); font-weight: 700; }
.champs td { border: 1px solid #ccc; padding: 5px 8px; }
.legende { width: 170px; background: #f2f4fa; font-weight: 600; }
h2 { font-size: 13px; color: var(--or); border-bottom: 2px solid var(--or); padding-bottom: 3px; }
table.besoins th { background: var(--bleu); color: #fff; }
table.besoins td { border: 1px solid #ddd; padding: 4px 8px; }
@media print {
  .no-print { display: none !important; }
  body { margin: 0; }
  .bandeau { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .signature td { border: 0; }
}
</style>
</head>
<body class="bg-light">
<!-- Barre d'action, masquée à l'impression -->
<div class="no-print text-center py-3">
  <button class="btn btn-primary" onclick="window.print()">🖨️ Imprimer</button>
  <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/intervention_detail.php?id=<?= (int)$int['id'] ?>">Retour au détail</a>
  <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/pdf_fiche.php?id=<?= (int)$int['id'] ?>">Version PDF</a>
</div>

<div class="bg-white mx-auto p-4" style="max-width: 800px">
  <div class="bandeau">
    <div>FICHE D'INTERVENTION <span class="or"><?= Security::e($int['reference']) ?></span></div>
    <div class="small">Service technique du CHU — document généré le <?= Security::e(date('d/m/Y H:i')) ?></div>
  </div>

  <table class="champs w-100 mt-3">
    <tr><td class="legende">Date et heure</td><td><?= Security::e($int['date_signalement']) ?></td>
        <td class="legende">Priorité</td><td><?= Security::e(LIBELLES_PRIORITE[$int['priorite']]) ?></td></tr>
    <tr><td class="legende">Service demandeur</td><td><?= Security::e($int['service_nom'] ?: $int['service_autre']) ?></td>
        <td class="legende">Branche demandée</td><td><?= Security::e($int['type_nom'] ?: $int['type_autre']) ?></td></tr>
    <tr><td class="legende">Personne qui a appelé</td><td><?= Security::e($int['nom_demandeur']) ?></td>
        <td class="legende">Téléphone</td><td><?= Security::e($int['telephone_demandeur']) ?></td></tr>
    <tr><td class="legende">Statut</td><td><?= Security::e(LIBELLES_STATUT[$int['statut']]) ?></td>
        <td class="legende">Technicien</td><td><?= Security::e($int['technicien_nom'] ?: '—') ?></td></tr>
  </table>

  <h2 class="mt-4">Description du problème</h2>
  <p style="white-space: pre-line"><?= Security::e($int['description_probleme']) ?></p>

  <h2 class="mt-3">Besoins en matériel</h2>
  <table class="besoins w-100">
    <tr><th>Matériel</th><th>Quantité</th><th>Cycle</th></tr>
    <?php if (!$besoins): ?><tr><td colspan="3">Aucun besoin enregistré.</td></tr><?php endif; ?>
    <?php foreach ($besoins as $b): ?>
      <tr><td><?= Security::e($b['description_besoin']) ?></td><td><?= (int)$b['quantite'] ?></td>
          <td><?= Security::e(LIBELLES_BESOIN[$b['statut']]) ?></td></tr>
    <?php endforeach; ?>
  </table>

  <?php if ($int['commentaire_resolution']): ?>
    <h2 class="mt-3">Résolution</h2>
    <p><?= Security::e($int['commentaire_resolution']) ?></p>
  <?php endif; ?>

  <h2 class="mt-3">Traçabilité</h2>
  <table class="champs w-100">
    <tr><td class="legende">Créée par</td><td><?= Security::e($int['cree_par']) ?></td>
        <td class="legende">Créée le</td><td><?= Security::e($int['created_at']) ?></td></tr>
    <tr><td class="legende">Dernière modification</td><td><?= Security::e($int['modifie_par'] ?: '—') ?></td>
        <td class="legende">Le</td><td><?= Security::e($int['updated_at']) ?></td></tr>
  </table>

  <table class="signature mt-5" style="width: 100%"><tr>
    <td style="width: 49%; vertical-align: bottom">Visa du demandeur<br><br>______________________</td>
    <td style="width: 2%"></td>
    <td style="width: 49%; vertical-align: bottom">Visa du technicien<br><br>______________________</td>
  </tr></table>
</div>
</body>
</html>

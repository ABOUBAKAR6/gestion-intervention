<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/init.php';
// Charge Dompdf et ses dépendances installées via Composer (dossier vendor/, exclu de Git)
require_once dirname(__DIR__) . '/vendor/autoload.php';
$u = Security::exigerConnexion();
$pdo = Database::pdo();

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
// Accès seulement après les contrôles habituels (connecté, actif, membre ou admin)
if (!$int || !peutAccederIntervention($u, $int)) {
    Journal::log((int)$u['id'], 'acces_refuse_pdf', 'intervention', (int)($_GET['id'] ?? 0));
    http_response_code(403);
    exit('Accès refusé.');
}

$besoins = $pdo->prepare(
    'SELECT b.* FROM besoins_materiel b WHERE b.intervention_id = ? ORDER BY b.id'
);
$besoins->execute([$int['id']]);
$besoins = $besoins->fetchAll();

$html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1b2a4a; }
  .bandeau { background:#1b2a4a; color:#fff; padding:12px 16px; }
  .bandeau h1 { margin:0; font-size:16px; } .bandeau .or { color:#c9a227; }
  table.champs { width:100%; border-collapse:collapse; margin-top:14px; }
  table.champs td { border:1px solid #ccc; padding:6px 8px; }
  td.legende { width:170px; background:#f2f4fa; font-weight:bold; }
  h2 { font-size:12px; color:#c9a227; border-bottom:2px solid #c9a227; padding-bottom:3px; margin:16px 0 6px; }
  table.besoins { width:100%; border-collapse:collapse; }
  table.besoins th { background:#1b2a4a; color:#fff; padding:5px; text-align:left; font-size:10px; }
  table.besoins td { border:1px solid #ddd; padding:5px 8px; }
  .pied { position:fixed; bottom:0; font-size:9px; color:#666; }
</style></head><body>

<div class="bandeau">
  <h1>FICHE D\'INTERVENTION <span class="or">' . Security::e($int['reference']) . '</span></h1>
  <div style="font-size:10px">Service technique du CHU — document généré le ' . Security::e(date('d/m/Y H:i')) . '</div>
</div>

<table class="champs">
<tr><td class="legende">Date et heure</td><td>' . Security::e($int['date_signalement']) . '</td>
    <td class="legende">Priorité</td><td>' . Security::e(LIBELLES_PRIORITE[$int['priorite']]) . '</td></tr>
<tr><td class="legende">Service demandeur</td><td>' . Security::e($int['service_nom'] ?: $int['service_autre']) . '</td>
    <td class="legende">Branche demandée</td><td>' . Security::e($int['type_nom'] ?: $int['type_autre']) . '</td></tr>
<tr><td class="legende">Personne qui a appelé</td><td>' . Security::e($int['nom_demandeur']) . '</td>
    <td class="legende">Téléphone</td><td>' . Security::e($int['telephone_demandeur']) . '</td></tr>
<tr><td class="legende">Statut</td><td>' . Security::e(LIBELLES_STATUT[$int['statut']]) . '</td>
    <td class="legende">Technicien</td><td>' . Security::e($int['technicien_nom'] ?: '—') . '</td></tr>
</table>

<h2>Description du problème</h2>
<p>' . Security::e($int['description_probleme']) . '</p>

<h2>Besoins en matériel</h2>
<table class="besoins">
<tr><th>Matériel</th><th>Quantité</th><th>Cycle</th></tr>
' . (empty($besoins) ? '<tr><td colspan="3">Aucun besoin enregistré.</td></tr>' : '') .
implode('', array_map(fn($b) => '<tr><td>' . Security::e($b['description_besoin']) . '</td>
  <td>' . (int)$b['quantite'] . '</td><td>' . Security::e(LIBELLES_BESOIN[$b['statut']]) . '</td></tr>', $besoins)) . '
</table>

' . ($int['commentaire_resolution'] ? '<h2>Résolution</h2><p>' . Security::e($int['commentaire_resolution']) . '</p>' : '') . '

<h2>Traçabilité</h2>
<table class="champs">
<tr><td class="legende">Créée par</td><td>' . Security::e($int['cree_par']) . '</td>
    <td class="legende">Créée le</td><td>' . Security::e($int['created_at']) . '</td></tr>
<tr><td class="legende">Dernière modification</td><td>' . Security::e($int['modifie_par'] ?: '—') . '</td>
    <td class="legende">Le</td><td>' . Security::e($int['updated_at']) . '</td></tr>
</table>

<div style="margin-top:50px">
  <table style="width:100%"><tr>
    <td style="width:49%">Visa du demandeur<br><br><br>______________________</td>
    <td style="width:2%"></td>
    <td style="width:49%">Visa du technicien<br><br><br>______________________</td>
  </tr></table>
</div>

<div class="pied"><span style="float:left">' . Security::e(APP_NOM) . '</span>
<span style="float:right">Fiche ' . Security::e($int['reference']) . '</span></div>
</body></html>';

// Génération du PDF côté serveur avec Dompdf
$dompdf = new Dompdf\Dompdf(['isRemoteEnabled' => false]);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4');
$dompdf->render();
Journal::log((int)$u['id'], Journal::PDF_GENERE, 'intervention', (int)$int['id'], $int['groupe_id'], 'Réf. ' . $int['reference']);
$dompdf->stream('fiche_' . $int['reference'] . '.pdf', ['Attachment' => false]);

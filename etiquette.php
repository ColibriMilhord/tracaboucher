<?php
// ============================================================
//  Vue imprimable d'un n° de lot (dépannage / archivage papier)
//  etiquette.php?type=entree|sortie&id=..
// ============================================================
require_once __DIR__ . '/includes/auth.php';
$moi = exiger_connexion();

$type = $_GET['type'] ?? '';
$id   = (int)($_GET['id'] ?? 0);

if ($type === 'entree') {
    $l = entree_par_id($id);
    if (!$l) { http_response_code(404); exit('Introuvable'); }
    $titre  = $l['type_libelle'];
    $infos  = [
        'Réception'   => fmt_date($l['date_entree']),
        'Fournisseur' => $l['fournisseur'],
        'Température' => fmt_temp((float)$l['temperature']),
        'Poids'       => $l['poids_kg'] === null ? '—' : fmt_qte((float)$l['poids_kg']),
    ];
} elseif ($type === 'sortie') {
    $l = sortie_par_id($id);
    if (!$l) { http_response_code(404); exit('Introuvable'); }
    $titre  = $l['produit'];
    $infos  = [
        'Fabriqué le'     => fmt_date($l['date_fabrication']),
        'Quantité'        => fmt_qte((float)$l['quantite'], $l['unite']),
        'Conditionnement' => LIB_CONDITIONNEMENT[$l['conditionnement']] ?? '',
        'Conservation'    => LIB_CONSERVATION[$l['conservation']] ?? '',
        'DLC / DDM'       => fmt_date($l['dlc']),
    ];
} else {
    http_response_code(400);
    exit('Type inconnu.');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($l['num_lot']) ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, "Segoe UI", sans-serif; background: #f0f0f0; padding: 24px;
         display: flex; flex-direction: column; align-items: center; gap: 16px; }
  .etq { background: #fff; border: 2px solid #000; border-radius: 6px; padding: 16px 20px;
         width: 320px; }
  .titre { font-size: 15px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px;
           border-bottom: 1px solid #000; padding-bottom: 6px; margin-bottom: 10px; }
  .lot { font-size: 34px; font-weight: 800; letter-spacing: 2px; text-align: center;
         font-family: "Courier New", monospace; margin: 6px 0 12px; }
  dl { display: grid; grid-template-columns: auto 1fr; gap: 4px 12px; font-size: 13px; }
  dt { color: #555; }
  dd { font-weight: 600; text-align: right; }
  .actions { display: flex; gap: 10px; }
  button, a.btn { font: inherit; font-size: 14px; padding: 10px 18px; border-radius: 999px;
                  border: none; background: #2c5530; color: #fff; cursor: pointer; text-decoration: none; }
  a.btn.sec { background: #e0e0e0; color: #2c2c2c; }
  @media print {
    body { background: #fff; padding: 0; }
    .actions { display: none; }
    .etq { border-width: 1px; }
  }
</style>
</head>
<body>
  <div class="etq">
    <div class="titre"><?= h($titre) ?></div>
    <div class="lot"><?= h($l['num_lot']) ?></div>
    <dl>
      <?php foreach ($infos as $k => $v): if ($v === '' || $v === '—') continue; ?>
      <dt><?= h($k) ?></dt><dd><?= h($v) ?></dd>
      <?php endforeach ?>
    </dl>
  </div>
  <div class="actions">
    <button onclick="window.print()">Imprimer</button>
    <a class="btn sec" href="<?= $type === 'entree' ? 'entrees.php?action=voir&id=' : 'fabrications.php?action=voir&id=' ?><?= $id ?>">Retour</a>
  </div>
</body>
</html>

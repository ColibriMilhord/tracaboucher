<?php
$page_active = 'dashboard';
$page_title  = 'Recherche & Traçabilité';
require_once __DIR__ . '/includes/db.php';

$pdo      = db();
$resultats= [];
$type_r   = $_GET['type'] ?? 'tracabilite';
$q        = trim($_GET['q'] ?? '');
$d_debut  = trim($_GET['d1'] ?? '');
$d_fin    = trim($_GET['d2'] ?? '');
$esp_f    = (int)($_GET['esp'] ?? 0);
$especes  = $pdo->query('SELECT * FROM especes ORDER BY libelle')->fetchAll();

if ($q || $d_debut || $d_fin || $esp_f) {
    switch ($type_r) {

        case 'lots':
            $sql='SELECT l.*,e.code,e.libelle,e.emoji,e.couleur FROM lots_carcasses l JOIN especes e ON e.id=l.espece_id WHERE 1=1';
            $p=[];
            if ($q)     { $sql.=' AND (l.num_lot LIKE ? OR l.fournisseur LIKE ? OR l.num_abattoir LIKE ?)'; $p=array_merge($p,["%$q%","%$q%","%$q%"]); }
            if ($d_debut){ $sql.=' AND l.date_entree>=?'; $p[]=$d_debut; }
            if ($d_fin)  { $sql.=' AND l.date_entree<=?'; $p[]=$d_fin; }
            if ($esp_f)  { $sql.=' AND l.espece_id=?';   $p[]=$esp_f; }
            $sql.=' ORDER BY l.date_entree DESC';
            $st=$pdo->prepare($sql); $st->execute($p); $resultats=$st->fetchAll();
            break;

        case 'sorties':
            $sql='SELECT s.*,l.num_lot,e.code,e.emoji,e.couleur FROM sorties_viande s JOIN lots_carcasses l ON l.id=s.lot_id JOIN especes e ON e.id=l.espece_id WHERE 1=1';
            $p=[];
            if ($q)     { $sql.=' AND (l.num_lot LIKE ? OR s.client LIKE ?)'; $p=array_merge($p,["%$q%","%$q%"]); }
            if ($d_debut){ $sql.=' AND s.date_sortie>=?'; $p[]=$d_debut; }
            if ($d_fin)  { $sql.=' AND s.date_sortie<=?'; $p[]=$d_fin; }
            if ($esp_f)  { $sql.=' AND l.espece_id=?';   $p[]=$esp_f; }
            $sql.=' ORDER BY s.date_sortie DESC';
            $st=$pdo->prepare($sql); $st->execute($p); $resultats=$st->fetchAll();
            break;

        case 'plats':
            $sql='SELECT * FROM plats_cuisines WHERE 1=1';
            $p=[];
            if ($q)     { $sql.=' AND (nom LIKE ? OR ref_lot_fini LIKE ?)'; $p=["%$q%","%$q%"]; }
            if ($d_debut){ $sql.=' AND date_preparation>=?'; $p[]=$d_debut; }
            if ($d_fin)  { $sql.=' AND date_preparation<=?'; $p[]=$d_fin; }
            $sql.=' ORDER BY date_preparation DESC';
            $st=$pdo->prepare($sql); $st->execute($p); $resultats=$st->fetchAll();
            break;

        case 'tracabilite':
        default:
            if ($q) {
                $st=$pdo->prepare('SELECT l.*,e.code,e.libelle,e.emoji,e.couleur FROM lots_carcasses l JOIN especes e ON e.id=l.espece_id WHERE l.num_lot LIKE ? OR l.num_abattoir LIKE ? OR l.fournisseur LIKE ?');
                $st->execute(["%$q%","%$q%","%$q%"]);
                $resultats=$st->fetchAll();
            }
            break;
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="main">

<!-- Formulaire -->
<div class="card">
  <div class="card-title">🔍 Recherche & Traçabilité</div>
  <form method="GET" action="recherche.php">

    <!-- Type -->
    <div class="form-group">
      <div class="form-label">Rechercher dans</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
        <?php foreach (['tracabilite'=>['🔗','Traçabilité'],'lots'=>['📦','Lots entrée'],'sorties'=>['↗️','Sorties'],'plats'=>['✂️','Plats']] as $val=>[$ico,$lab]): ?>
        <label style="cursor:pointer">
          <input type="radio" name="type" value="<?= $val ?>" <?= $type_r===$val?'checked':'' ?> style="display:none" onchange="toggleEsp(this.value)">
          <div class="rtype" style="text-align:center;padding:12px 8px;border-radius:12px;
               border:2px solid <?= $type_r===$val?'var(--rouge)':'var(--gris-l)' ?>;
               background:<?= $type_r===$val?'var(--rouge-bg)':'#fff' ?>;
               color:<?= $type_r===$val?'var(--rouge)':'var(--gris)' ?>;
               font-size:13px;font-weight:700;transition:all .15s"
               onclick="this.parentElement.querySelector('input').checked=true;toggleEsp(<?= json_encode($val) ?>);styleTypes(this)">
            <div style="font-size:20px;margin-bottom:3px"><?= $ico ?></div>
            <?= $lab ?>
          </div>
        </label>
        <?php endforeach ?>
      </div>
    </div>

    <!-- Mot-clé -->
    <div class="form-group">
      <div class="form-label">Mot-clé</div>
      <div style="position:relative">
        <span style="position:absolute;left:13px;top:50%;transform:translateY(-50%);font-size:16px">🔍</span>
        <input type="text" name="q" class="fc" style="padding-left:40px"
               value="<?= h($q) ?>" placeholder="N° lot, fournisseur, client, plat…">
      </div>
    </div>

    <!-- Dates -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <div class="form-group">
        <div class="form-label">Du</div>
        <input type="date" name="d1" class="fc" value="<?= h($d_debut) ?>">
      </div>
      <div class="form-group">
        <div class="form-label">Au</div>
        <input type="date" name="d2" class="fc" value="<?= h($d_fin) ?>">
      </div>
    </div>

    <!-- Espèce -->
    <div class="form-group" id="especeZone" style="<?= in_array($type_r,['lots','sorties','tracabilite'])?'':'display:none' ?>">
      <div class="form-label">Espèce</div>
      <select name="esp" class="fc">
        <option value="">Toutes espèces</option>
        <?php foreach ($especes as $e): ?>
        <option value="<?= $e['id'] ?>" <?= $esp_f==$e['id']?'selected':'' ?>><?= $e['emoji'] ?> <?= h($e['libelle']) ?></option>
        <?php endforeach ?>
      </select>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <a href="recherche.php" class="btn btn-gris" style="text-align:center">✕ Effacer</a>
      <button type="submit" class="btn btn-rouge">🔍 Rechercher</button>
    </div>
  </form>
</div>

<!-- ── RÉSULTATS ── -->
<?php if ($resultats !== []): ?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
  <div class="sec-title" style="margin:0"><?= count($resultats) ?> résultat(s)</div>
  <button onclick="window.print()" class="btn btn-outline btn-sm no-print">🖨️ Imprimer</button>
</div>

<?php if ($type_r==='lots'): ?>
<!-- Résultats lots -->
<?php foreach ($resultats as $r):
  $s=solde_lot($r['id']);
  $p=$r['poids_carcasse_kg']>0?min(100,round((($r['poids_carcasse_kg']-$s)/$r['poids_carcasse_kg'])*100)):0;
?>
<a href="lots.php?id=<?= $r['id'] ?>" class="lot-item" style="border-left-color:<?= h($r['couleur']) ?>">
  <span style="font-size:26px"><?= $r['emoji'] ?></span>
  <div style="flex:1;min-width:0">
    <div class="lot-num"><?= h($r['num_lot']) ?></div>
    <div class="lot-meta"><?= fmt_date($r['date_entree']) ?><?= $r['fournisseur']?' · '.h($r['fournisseur']):'' ?></div>
    <div class="progress" style="margin-top:4px;height:4px">
      <div class="pbar" style="width:<?= $p ?>%"></div>
    </div>
  </div>
  <div style="text-align:right">
    <div style="font-weight:900;color:var(--rouge)"><?= number_format($r['poids_carcasse_kg'],0) ?> kg</div>
    <div style="font-size:11px;font-weight:700;color:<?= $s<=0?'var(--rouge)':'#1A6B3C' ?>">▸ <?= number_format($s,1,',') ?> kg</div>
  </div>
</a>
<?php endforeach ?>

<?php elseif ($type_r==='sorties'): ?>
<!-- Résultats sorties -->
<?php $tot=array_sum(array_column($resultats,'poids_kg')); ?>
<div style="background:var(--rouge-bg);border:1px solid var(--rouge-bd);border-radius:12px;padding:12px;margin-bottom:10px;font-size:14px">
  Total : <strong><?= number_format($tot,2,',') ?> kg</strong> sur <?= count($resultats) ?> sortie(s)
</div>
<div class="card" style="padding:0">
  <div class="tw">
    <table>
      <thead><tr><th>Date</th><th>Lot</th><th>Type</th><th>Client</th><th style="text-align:right">Poids</th></tr></thead>
      <tbody>
      <?php foreach ($resultats as $r): ?>
      <tr>
        <td><?= fmt_date($r['date_sortie']) ?></td>
        <td><a href="lots.php?id=<?= $r['lot_id'] ?>" style="font-weight:800;color:var(--rouge);font-size:12px"><?= $r['emoji'] ?> <?= h($r['num_lot']) ?></a></td>
        <td><?= $r['type_sortie']==='vente_directe'?'🛒':($r['type_sortie']==='traiteur'?'✂️':'📦') ?></td>
        <td style="font-size:12px"><?= h($r['client']??'—') ?></td>
        <td style="text-align:right;font-weight:900;color:var(--rouge)"><?= number_format($r['poids_kg'],2,',') ?> kg</td>
      </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($type_r==='plats'): ?>
<!-- Résultats plats -->
<?php foreach ($resultats as $p): ?>
<a href="traiteur.php?plat_id=<?= $p['id'] ?>" class="lot-item">
  <span style="font-size:26px">✂️</span>
  <div style="flex:1;min-width:0">
    <div class="lot-num"><?= h($p['nom']) ?></div>
    <div class="lot-meta"><?= h($p['ref_lot_fini']??'—') ?> · <?= fmt_date($p['date_preparation']) ?></div>
  </div>
  <?php if ($p['quantite_kg']): ?><div style="font-weight:900;color:var(--rouge)"><?= number_format($p['quantite_kg'],1,',') ?> kg</div><?php endif ?>
</a>
<?php endforeach ?>

<?php else: ?>
<!-- ── TRAÇABILITÉ COMPLÈTE ── -->
<?php foreach ($resultats as $r):
  $solde=$r['poids_carcasse_kg'];
  $sq=$pdo->prepare('SELECT * FROM sorties_viande WHERE lot_id=? ORDER BY date_sortie');
  $sq->execute([$r['id']]); $srt=$sq->fetchAll();
  $pq=$pdo->prepare('SELECT pv.*,pc.nom,pc.date_preparation,pc.ref_lot_fini FROM plats_viande pv JOIN plats_cuisines pc ON pc.id=pv.plat_id WHERE pv.lot_id=?');
  $pq->execute([$r['id']]); $pls=$pq->fetchAll();
  $tot_out=array_sum(array_column($srt,'poids_kg'))+array_sum(array_column($pls,'poids_kg'));
  $reste=max(0,$r['poids_carcasse_kg']-$tot_out);
  $pct=$r['poids_carcasse_kg']>0?min(100,round($tot_out/$r['poids_carcasse_kg']*100)):0;
?>
<div class="card" style="border-left:4px solid <?= h($r['couleur']) ?>;padding:14px">
  <!-- En-tête lot -->
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
    <span style="font-size:28px"><?= $r['emoji'] ?></span>
    <div style="flex:1">
      <div style="font-size:18px;font-weight:900"><?= h($r['num_lot']) ?></div>
      <div style="font-size:12px;color:var(--gris)"><?= fmt_date($r['date_entree']) ?><?= $r['fournisseur']?' · '.h($r['fournisseur']):'' ?></div>
      <?php if ($r['num_abattoir']): ?>
      <div style="font-size:11px;color:var(--gris)">Abattoir: <?= h($r['num_abattoir']) ?><?= $r['origine']?' · '.h($r['origine']):'' ?></div>
      <?php endif ?>
    </div>
    <div style="text-align:right">
      <div style="font-weight:900;color:var(--rouge)"><?= number_format($r['poids_carcasse_kg'],1,',') ?> kg</div>
      <div style="font-size:11px;font-weight:700;color:<?= $reste<=0?'var(--rouge)':'#1A6B3C' ?>">▸ <?= number_format($reste,1,',') ?> kg</div>
    </div>
  </div>
  <div class="progress" style="margin-bottom:12px">
    <div class="pbar <?= $pct>=95?'':($pct>=70?'mid':'low') ?>" style="width:<?= $pct ?>%"></div>
  </div>

  <?php if ($srt||$pls): ?>
  <div style="font-size:11px;font-weight:800;color:var(--gris);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Traçabilité des sorties</div>
  <?php foreach ($srt as $sv): ?>
  <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 10px;background:var(--fond);border-radius:8px;margin-bottom:5px;font-size:13px">
    <div>
      <span><?= $sv['type_sortie']==='vente_directe'?'🛒':($sv['type_sortie']==='traiteur'?'✂️':'📦') ?></span>
      <span style="font-weight:600"><?= fmt_date($sv['date_sortie']) ?></span>
      <?php if ($sv['client']): ?><span style="color:var(--gris)"> · <?= h($sv['client']) ?></span><?php endif ?>
    </div>
    <strong style="color:var(--rouge)"><?= number_format($sv['poids_kg'],2,',') ?> kg</strong>
  </div>
  <?php endforeach ?>
  <?php foreach ($pls as $pv): ?>
  <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 10px;background:#FEF9ED;border-radius:8px;margin-bottom:5px;font-size:13px">
    <div>
      <span>✂️</span>
      <a href="traiteur.php?plat_id=<?= $pv['plat_id'] ?>" style="color:var(--rouge);font-weight:700;text-decoration:none"><?= h($pv['nom']) ?></a>
      <span style="color:var(--gris);font-size:11px"> · <?= fmt_date($pv['date_preparation']) ?></span>
      <?php if ($pv['ref_lot_fini']): ?><div style="font-size:10px;color:var(--gris);padding-left:18px">Réf: <?= h($pv['ref_lot_fini']) ?></div><?php endif ?>
    </div>
    <strong style="color:var(--rouge)"><?= number_format($pv['poids_kg'],2,',') ?> kg</strong>
  </div>
  <?php endforeach ?>
  <?php else: ?>
  <div style="font-size:13px;color:var(--gris);font-style:italic">Aucune sortie enregistrée.</div>
  <?php endif ?>

  <?php if ($r['doc_abattoir']): ?>
  <div style="margin-top:10px">
    <a href="<?= UPLOAD_URL.h($r['doc_abattoir']) ?>" target="_blank" class="btn btn-outline btn-sm">📄 Document abattoir</a>
  </div>
  <?php endif ?>
</div>
<?php endforeach ?>
<?php endif ?>

<?php elseif ($q||$d_debut||$d_fin): ?>
<div class="alert alert-info">Aucun résultat pour cette recherche.</div>
<?php endif ?>

</div>

<script>
function toggleEsp(val) {
  const z=document.getElementById('especeZone');
  z.style.display=['lots','sorties','tracabilite'].includes(val)?'':'none';
}
function styleTypes(el) {
  document.querySelectorAll('.rtype').forEach(b=>{
    b.style.background='#fff'; b.style.borderColor='var(--gris-l)'; b.style.color='var(--gris)';
  });
  el.style.background='var(--rouge-bg)';
  el.style.borderColor='var(--rouge)';
  el.style.color='var(--rouge)';
}
</script>
</body></html>

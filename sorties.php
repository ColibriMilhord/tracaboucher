<?php
$page_active = 'carcasse';
$page_title  = 'Sorties Viande';
require_once __DIR__ . '/includes/db.php';

$pdo    = db();
$msg    = ''; $msg_type='ok';
$action = $_GET['action'] ?? '';
$lot_id_pre = (int)($_GET['lot_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_sortie'])) {
    $lot_id  = (int)$_POST['lot_id'];
    $date_s  = trim($_POST['date_sortie']);
    $type_s  = trim($_POST['type_sortie']);
    $client  = trim($_POST['client']);
    $poids   = (float)str_replace(',','.',$_POST['poids_kg']);
    $prix_kg = $_POST['prix_kg']!=='' ? (float)str_replace(',','.',$_POST['prix_kg']) : null;
    $notes   = trim($_POST['notes']);
    $solde   = solde_lot($lot_id);
    if ($poids>$solde) {
        $msg='⚠️ Poids saisi ('.fmt_poids($poids).') > disponible ('.fmt_poids($solde).')'; $msg_type='err';
    } else {
        $pdo->prepare('INSERT INTO sorties_viande (lot_id,date_sortie,type_sortie,client,poids_kg,prix_kg,notes) VALUES (?,?,?,?,?,?,?)')
            ->execute([$lot_id,$date_s,$type_s,$client?:null,$poids,$prix_kg,$notes?:null]);
        $msg='✅ Sortie enregistrée.'; $action='';
    }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['del_sortie'])) {
    $pdo->prepare('DELETE FROM sorties_viande WHERE id=?')->execute([(int)$_POST['del_id']]);
    $msg='Sortie supprimée.';
}

$filtre = $_GET['type'] ?? '';
$sql='SELECT s.*,l.num_lot,e.code,e.emoji,e.couleur FROM sorties_viande s JOIN lots_carcasses l ON l.id=s.lot_id JOIN especes e ON e.id=l.espece_id';
$params=[];
if ($filtre) { $sql.=' WHERE s.type_sortie=?'; $params[]=$filtre; }
$sql.=' ORDER BY s.date_sortie DESC,s.id DESC';
$st=$pdo->prepare($sql); $st->execute($params);
$sorties=$st->fetchAll();

$lots_dispo=$pdo->query('SELECT l.*,e.code,e.emoji,e.couleur FROM lots_carcasses l JOIN especes e ON e.id=l.espece_id ORDER BY l.date_entree DESC')->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<div class="main">
<?php if ($msg): ?><div class="alert alert-<?= $msg_type ?>"><?= h($msg) ?></div><?php endif ?>

<!-- Filtres type -->
<div style="display:flex;gap:8px;overflow-x:auto;padding-bottom:4px;margin-bottom:12px;scrollbar-width:none">
  <?php foreach ([''=> 'Toutes','vente_directe'=>'🛒 Vente','traiteur'=>'✂️ Traiteur','autre'=>'📦 Autre'] as $val=>$lab): ?>
  <a href="sorties.php<?= $val?'?type='.$val:'' ?>" style="flex-shrink:0;padding:8px 14px;border-radius:20px;font-size:12px;font-weight:700;text-decoration:none;background:<?= $filtre===$val?'var(--rouge)':'var(--gris-l)' ?>;color:<?= $filtre===$val?'#fff':'var(--gris)' ?>"><?= $lab ?></a>
  <?php endforeach ?>
</div>

<!-- Totaux -->
<?php
$tot_kg  = array_sum(array_column($sorties,'poids_kg'));
$tot_val = array_reduce($sorties,fn($c,$s)=>$c+($s['prix_kg']?$s['poids_kg']*$s['prix_kg']:0),0);
?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
  <div class="card" style="text-align:center;padding:12px">
    <div style="font-size:22px;font-weight:900;color:var(--rouge)"><?= number_format($tot_kg,1,',') ?> kg</div>
    <div style="font-size:11px;color:var(--gris)">Total sorti</div>
  </div>
  <div class="card" style="text-align:center;padding:12px">
    <div style="font-size:22px;font-weight:900;color:#7A4A0A"><?= number_format($tot_val,0,',',' ') ?> €</div>
    <div style="font-size:11px;color:var(--gris)">Valeur estimée</div>
  </div>
</div>

<!-- Liste -->
<?php if ($sorties): ?>
<div class="card" style="padding:0">
  <div class="tw">
    <table>
      <thead><tr><th>Date</th><th>Lot</th><th>Type</th><th style="text-align:right">Poids</th><th class="no-print"></th></tr></thead>
      <tbody>
      <?php foreach ($sorties as $s): ?>
      <tr>
        <td style="white-space:nowrap"><?= fmt_date($s['date_sortie']) ?></td>
        <td>
          <a href="lots.php?id=<?= $s['lot_id'] ?>" style="font-weight:800;color:var(--rouge);font-size:12px">
            <?= $s['emoji'] ?> <?= h($s['num_lot']) ?>
          </a>
        </td>
        <td>
          <?= $s['type_sortie']==='vente_directe'?'🛒':($s['type_sortie']==='traiteur'?'✂️':'📦') ?>
          <?php if ($s['client']): ?><div style="font-size:11px;color:var(--gris)"><?= h($s['client']) ?></div><?php endif ?>
        </td>
        <td style="text-align:right;font-weight:900;color:var(--rouge)"><?= number_format($s['poids_kg'],2,',') ?> kg
          <?php if ($s['prix_kg']): ?><div style="font-size:10px;color:var(--gris)"><?= number_format($s['prix_kg'],2,',') ?> €/kg</div><?php endif ?>
        </td>
        <td class="no-print">
          <form method="POST" onsubmit="return confirm('Supprimer ?')">
            <input type="hidden" name="del_id" value="<?= $s['id'] ?>">
            <button type="submit" name="del_sortie" style="background:var(--rouge-bg);color:var(--rouge);border:none;border-radius:8px;padding:5px 8px;cursor:pointer">🗑</button>
          </form>
        </td>
      </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?><div class="alert alert-info">Aucune sortie enregistrée.</div><?php endif ?>

<a href="sorties.php?action=ajouter" class="fab no-print">+</a>
</div>

<!-- MODALE SORTIE -->
<div class="modal-bg <?= $action==='ajouter'?'open':'' ?>" id="mSortie">
  <div class="modal">
    <div class="modal-grip"></div>
    <div class="modal-title">↗️ Nouvelle Sortie Viande</div>
    <form method="POST" action="sorties.php">

    <div class="form-group">
      <div class="form-label">Lot carcasse *</div>
      <select name="lot_id" class="fc" required onchange="showSolde(this)">
        <option value="">— Sélectionner —</option>
        <?php foreach ($lots_dispo as $l):
          $s=solde_lot($l['id']);
        ?>
        <option value="<?= $l['id'] ?>" data-solde="<?= $s ?>"
                <?= $lot_id_pre==$l['id']?'selected':'' ?>
                <?= $s<=0?'style="color:var(--gris)"':'' ?>>
          <?= $l['emoji'] ?> [<?= h($l['code']) ?>] <?= h($l['num_lot']) ?> · dispo: <?= number_format($s,1,',') ?> kg
        </option>
        <?php endforeach ?>
      </select>
      <div id="soldeInfo" style="margin-top:5px;font-size:13px;font-weight:700;color:var(--rouge)"></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <div class="form-group">
        <div class="form-label">Date *</div>
        <input type="date" name="date_sortie" class="fc" required value="<?= date('Y-m-d') ?>">
      </div>
      <div class="form-group">
        <div class="form-label">Type *</div>
        <select name="type_sortie" class="fc">
          <option value="vente_directe">🛒 Vente directe</option>
          <option value="traiteur">✂️ Traiteur</option>
          <option value="autre">📦 Autre</option>
        </select>
      </div>
    </div>

    <div class="form-group">
      <div class="form-label">Client / Destination</div>
      <input type="text" name="client" class="fc" placeholder="Nom client…">
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <div class="form-group">
        <div class="form-label">Poids (kg) *</div>
        <input type="number" name="poids_kg" class="fc" step="0.01" min="0.01" required placeholder="0.00">
      </div>
      <div class="form-group">
        <div class="form-label">Prix/kg (€)</div>
        <input type="number" name="prix_kg" class="fc" step="0.01" placeholder="0.00">
      </div>
    </div>

    <div class="form-group">
      <div class="form-label">Notes</div>
      <textarea name="notes" class="fc" placeholder="Découpe, morceau…"></textarea>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <button type="button" class="btn btn-gris" onclick="fermerSortie()">Annuler</button>
      <button type="submit" name="save_sortie" class="btn btn-rouge">✓ Enregistrer</button>
    </div>
    </form>
  </div>
</div>

<script>
function showSolde(sel) {
  const opt=sel.options[sel.selectedIndex];
  const s=parseFloat(opt.dataset.solde||0);
  const info=document.getElementById('soldeInfo');
  if(sel.value) info.textContent=s>0?'✅ Disponible: '+s.toFixed(2).replace('.',',')+' kg':'⚠️ Stock épuisé';
  else info.textContent='';
}
function fermerSortie(){document.getElementById('mSortie').classList.remove('open');history.replaceState(null,'','sorties.php');}
document.getElementById('mSortie').addEventListener('click',e=>{if(e.target===document.getElementById('mSortie'))fermerSortie();});
window.addEventListener('load',()=>{const s=document.querySelector('select[name=lot_id]');if(s&&s.value)showSolde(s);});
</script>
</body></html>

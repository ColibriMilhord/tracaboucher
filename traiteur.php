<?php
$page_active = 'plats';
$page_title  = 'Plats & Traiteur';
$page_title_top = 'BoucherTraçabilité';
require_once __DIR__ . '/includes/db.php';

$pdo     = db();
$msg     = ''; $msg_type = 'ok';
$action  = $_GET['action'] ?? '';
$plat_id = (int)($_GET['plat_id'] ?? 0);
$vue     = $_GET['vue'] ?? 'plats';

// ── POST : Nouveau plat
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_plat'])) {
    $nom    = trim($_POST['nom']);
    $date_p = trim($_POST['date_preparation']);
    $qte    = $_POST['quantite_kg']!=='' ? (float)str_replace(',','.',$_POST['quantite_kg']) : null;
    $nbp    = $_POST['nb_portions']!=='' ? (int)$_POST['nb_portions'] : null;
    $cond   = trim($_POST['conditionnement'] ?? 'vrac');
    $temp_c = trim($_POST['temperature_conservation'] ?? 'frais');
    $notes  = trim($_POST['notes']);
    $ref    = trim($_POST['ref_lot_fini'] ?? '');
    
    if (empty($ref)) {
        $ref = generer_ref_lot_fini($date_p);
    }

    try {
        $pdo->prepare(
            'INSERT INTO plats_cuisines (ref_lot_fini,nom,date_preparation,quantite_kg,nb_portions,conditionnement,temperature_conservation,notes)
             VALUES (?,?,?,?,?,?,?,?)'
        )->execute([$ref,$nom,$date_p,$qte,$nbp,$cond,$temp_c,$notes?:null]);
        $plat_id = (int)$pdo->lastInsertId();

        // Viandes
        foreach (($_POST['viande_lot']??[]) as $i=>$lid) {
            $lid   = (int)$lid;
            $poids = (float)str_replace(',','.',($_POST['viande_poids'][$i]??0));
            if ($lid>0 && $poids>0) {
                $pdo->prepare('INSERT INTO sorties_viande (lot_id,date_sortie,type_sortie,client,poids_kg) VALUES (?,?,\'traiteur\',?,?)')
                    ->execute([$lid,$date_p,$nom,$poids]);
                $pdo->prepare('INSERT INTO plats_viande (plat_id,lot_id,poids_kg) VALUES (?,?,?)')
                    ->execute([$plat_id,$lid,$poids]);
            }
        }

        // Ingrédients
        foreach (($_POST['ingr_ligne']??[]) as $i=>$lid) {
            $lid  = (int)$lid;
            $qtu  = (float)str_replace(',','.',($_POST['ingr_qte'][$i]??0));
            $unit = trim($_POST['ingr_unit'][$i]??'kg');
            if ($lid>0 && $qtu>0)
                $pdo->prepare('INSERT INTO plats_ingredients (plat_id,achat_ligne_id,quantite_utilisee,unite_affichee) VALUES (?,?,?,?)')
                    ->execute([$plat_id,$lid,$qtu,$unit]);
        }

        header('Location: traiteur.php?vue=plats&msg=ok');
        exit;
    } catch (Exception $e) {
        $msg = 'Erreur d\'enregistrement : ' . $e->getMessage(); $msg_type = 'err';
    }
}

// ── POST : Achat ingrédients
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_achat'])) {
    $num_f = trim($_POST['num_facture']);
    $date_a= trim($_POST['date_achat']);
    $fourn = trim($_POST['fournisseur']);
    $mtt   = $_POST['montant_total']!=='' ? (float)str_replace(',','.',$_POST['montant_total']) : null;
    $notes = trim($_POST['notes']);
    $doc_p = null;
    if (!empty($_FILES['doc_facture']['name'])) $doc_p=upload_fichier($_FILES['doc_facture'],'factures');

    try {
        $pdo->prepare('INSERT INTO achats_ingredients (num_facture,date_achat,fournisseur,montant_total,doc_facture,notes) VALUES (?,?,?,?,?,?)')
            ->execute([$num_f?:null,$date_a,$fourn?:null,$mtt,$doc_p,$notes?:null]);
        $achat_id=(int)$pdo->lastInsertId();

        foreach (($_POST['l_ingredient']??[]) as $i=>$ing_id) {
            $ing_id=(int)$ing_id;
            $qte   =(float)str_replace(',','.',($_POST['l_quantite'][$i]??0));
            $pu    =($_POST['l_prix'][$i]!=='') ? (float)str_replace(',','.',($_POST['l_prix'][$i]??'')) : null;
            $ref_l =trim($_POST['l_ref'][$i]??'');
            if ($ing_id>0&&$qte>0)
                $pdo->prepare('INSERT INTO achats_lignes (achat_id,ingredient_id,ref_lot_ing,quantite,prix_unitaire) VALUES (?,?,?,?,?)')
                    ->execute([$achat_id,$ing_id,$ref_l?:null,$qte,$pu]);
        }
        header('Location: traiteur.php?vue=achats&msg=achat_ok');
        exit;
    } catch (Exception $e) {
        $msg = 'Erreur : ' . $e->getMessage(); $msg_type = 'err';
    }
}

// ── GET msg checks
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'ok') $msg = '✅ Préparation enregistrée avec succès !';
    elseif ($_GET['msg'] === 'achat_ok') $msg = '✅ Facture d\'achat enregistrée.';
}

// ── Données pour formulaires
$lots_dispo = $pdo->query(
    'SELECT l.*,e.code,e.libelle,e.emoji,e.couleur FROM lots_carcasses l JOIN especes e ON e.id=l.espece_id ORDER BY l.date_entree DESC'
)->fetchAll();

$ingredients = $pdo->query('SELECT * FROM ingredients ORDER BY categorie,nom')->fetchAll();

$lignes_dispo = $pdo->query(
    'SELECT al.*,i.nom,i.unite,ai.num_facture,ai.date_achat,ai.fournisseur
     FROM achats_lignes al
     JOIN ingredients i ON i.id=al.ingredient_id
     JOIN achats_ingredients ai ON ai.id=al.achat_id
     ORDER BY ai.date_achat DESC,i.nom'
)->fetchAll();

// ── Détail plat
$detail_plat = null;
if ($plat_id) {
    $q=$pdo->prepare('SELECT * FROM plats_cuisines WHERE id=?'); $q->execute([$plat_id]);
    $detail_plat=$q->fetch();
    if ($detail_plat) {
        $qv=$pdo->prepare('SELECT pv.*,l.num_lot,l.origine,e.code,e.emoji,e.couleur FROM plats_viande pv JOIN lots_carcasses l ON l.id=pv.lot_id JOIN especes e ON e.id=l.espece_id WHERE pv.plat_id=?');
        $qv->execute([$plat_id]); $detail_plat['viandes']=$qv->fetchAll();
        $qi=$pdo->prepare('SELECT pi.*,al.ref_lot_ing,i.nom,i.unite,ai.num_facture,ai.date_achat FROM plats_ingredients pi JOIN achats_lignes al ON al.id=pi.achat_ligne_id JOIN ingredients i ON i.id=al.ingredient_id JOIN achats_ingredients ai ON ai.id=al.achat_id WHERE pi.plat_id=?');
        $qi->execute([$plat_id]); $detail_plat['ingredients']=$qi->fetchAll();
    }
}

// ── Listes
$plats  = $pdo->query('SELECT * FROM plats_cuisines ORDER BY date_preparation DESC')->fetchAll();
$achats = $pdo->query('SELECT * FROM achats_ingredients ORDER BY date_achat DESC')->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?>
<div class="<?= $msg_type === 'ok' ? 'bg-primary-container text-on-primary-container border-primary' : 'bg-error-container text-error border-error' ?> p-4 rounded-xl mb-4 font-bold border">
  <?= h($msg) ?>
</div>
<?php endif ?>

<?php if ($plat_id && $detail_plat):
// ════════════════════ DÉTAIL PLAT ════════════════════
?>
<a href="traiteur.php?vue=plats" class="text-primary font-bold hover:underline mb-4 inline-block">← Retour</a>

<div class="bg-surface-container-lowest border-l-4 border-primary rounded-r-xl p-4 md:p-6 shadow-sm mb-6 border-y border-r border-outline-variant">
  <div class="text-2xl font-black text-on-surface mb-2">✂️ <?= h($detail_plat['nom']) ?></div>
  <div class="text-sm text-on-surface-variant mb-4">
    Réf: <strong class="text-on-surface"><?= h($detail_plat['ref_lot_fini']??'—') ?></strong> ·
    <?= fmt_date($detail_plat['date_preparation']) ?> ·
    <span class="font-bold">
      <?= $detail_plat['conditionnement']==='mise_sous_vide'?'🫙 Mise sous vide':($detail_plat['conditionnement']==='barquette'?'📦 Barquette':'📤 Vrac') ?>
    </span>
  </div>
  <?php if ($detail_plat['quantite_kg']||$detail_plat['nb_portions']): ?>
  <div class="flex gap-4">
    <?php if ($detail_plat['quantite_kg']): ?>
    <div class="bg-surface-container-low border border-outline-variant rounded-xl px-4 py-2 text-center flex-1">
      <div class="text-2xl font-black text-primary"><?= number_format($detail_plat['quantite_kg'],1,',') ?></div>
      <div class="text-xs text-on-surface-variant uppercase font-bold mt-1">kg produits</div>
    </div>
    <?php endif ?>
    <?php if ($detail_plat['nb_portions']): ?>
    <div class="bg-surface-container-low border border-outline-variant rounded-xl px-4 py-2 text-center flex-1">
      <div class="text-2xl font-black text-primary"><?= $detail_plat['nb_portions'] ?></div>
      <div class="text-xs text-on-surface-variant uppercase font-bold mt-1">portions</div>
    </div>
    <?php endif ?>
  </div>
  <?php endif ?>
</div>

<h3 class="font-headline-md text-xl font-bold text-on-surface mb-4">🥩 Viande utilisée (Origine)</h3>
<div class="flex flex-col gap-3 mb-6">
  <?php if ($detail_plat['viandes']): ?>
  <?php foreach ($detail_plat['viandes'] as $v): ?>
  <div class="flex items-center gap-4 border rounded-xl p-4" style="border-color:<?= h($v['couleur']) ?>50;background:<?= h($v['couleur']) ?>0a">
    <span class="text-3xl"><?= $v['emoji'] ?></span>
    <div class="flex-1">
      <div class="font-bold text-on-surface"><?= h($v['code']) ?> – <?= h($v['num_lot']) ?></div>
      <div class="text-xs text-on-surface-variant mt-1">Origine : <?= h($v['origine']??'France') ?></div>
    </div>
    <div class="text-lg font-black text-primary"><?= number_format($v['poids_kg'],2,',') ?> kg</div>
  </div>
  <?php endforeach ?>
  <?php else: ?><div class="bg-surface-container-low text-on-surface-variant p-4 rounded-xl border border-outline-variant">Aucune viande liée.</div><?php endif ?>
</div>

<h3 class="font-headline-md text-xl font-bold text-on-surface mb-4">🧂 Ingrédients & Épices</h3>
<div class="flex flex-col gap-3 mb-6">
  <?php if ($detail_plat['ingredients']): ?>
  <?php foreach ($detail_plat['ingredients'] as $ing): ?>
  <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 flex justify-between items-center shadow-sm">
    <div>
      <div class="font-bold text-on-surface"><?= h($ing['nom']) ?></div>
      <?php if ($ing['ref_lot_ing']): ?>
      <div class="text-xs text-on-surface-variant mt-1">Lot: <?= h($ing['ref_lot_ing']) ?></div>
      <?php elseif ($ing['num_facture']): ?>
      <div class="text-xs text-on-surface-variant mt-1">Facture: <?= h($ing['num_facture']) ?> · <?= fmt_date($ing['date_achat']) ?></div>
      <?php endif ?>
    </div>
    <div class="text-lg font-black text-secondary">
      <?= number_format($ing['quantite_utilisee'],$ing['unite_affichee']==='g'?0:3,',') ?>
      <span class="text-xs font-medium text-on-surface-variant"><?= h($ing['unite_affichee']??$ing['unite']) ?></span>
    </div>
  </div>
  <?php endforeach ?>
  <?php else: ?><div class="bg-surface-container-low text-on-surface-variant p-4 rounded-xl border border-outline-variant">Aucun ingrédient lié.</div><?php endif ?>
</div>

<div class="text-center mt-8 no-print mb-8">
  <button onclick="window.print()" class="bg-surface-variant text-on-surface-variant font-bold py-3 px-6 rounded-xl hover:bg-outline-variant transition-colors flex items-center justify-center gap-2 mx-auto">
    <span class="material-symbols-outlined">print</span> Fiche traçabilité
  </button>
</div>

<?php elseif ($action === 'plat'):
// ════════════════════ FORMULAIRE SIMPLIFIÉ NOUVEAU PLAT ════════════════════
?>
<header class="mb-6">
  <h2 class="font-display text-2xl font-extrabold text-primary">Nouveau Plat</h2>
  <p class="font-label-md text-on-surface-variant uppercase tracking-wide mt-1">Assemblage & Traçabilité</p>
</header>

<form method="POST" action="traiteur.php?action=plat" class="flex flex-col gap-6">

  <!-- Section 1 : Produit Fini -->
  <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 md:p-6 shadow-sm">
    <div class="text-sm font-bold text-primary flex items-center gap-2 mb-4 uppercase tracking-wider">
      <span>🍴</span> Produit Fini
    </div>

    <div class="mb-4">
      <label class="block text-xs font-bold text-on-surface-variant uppercase mb-2">Nom du Plat *</label>
      <input type="text" name="nom" class="w-full px-4 py-3 border border-outline-variant rounded-xl bg-surface focus:border-primary focus:ring-1 focus:ring-primary outline-none" placeholder="ex: Couscous Royal" required>
    </div>

    <div class="mb-4">
      <label class="block text-xs font-bold text-on-surface-variant uppercase mb-2">Référence Lot Fini (Auto)</label>
      <div class="flex items-center gap-2">
        <input type="text" id="refAuto" name="ref_lot_fini" class="flex-1 px-4 py-3 border border-outline-variant rounded-xl bg-surface-container-low text-on-surface font-mono font-bold outline-none" value="TR-<?= date('Ymd') ?>-001" readonly>
        <button type="button" onclick="regenRef()" class="p-3 bg-surface-variant rounded-xl hover:bg-outline-variant transition-colors" title="Régénérer">
          <span class="material-symbols-outlined">refresh</span>
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
      <div>
        <label class="block text-xs font-bold text-on-surface-variant uppercase mb-2">Date préparation *</label>
        <input type="date" name="date_preparation" class="w-full px-4 py-3 border border-outline-variant rounded-xl bg-surface focus:border-primary focus:ring-1 focus:ring-primary outline-none" required value="<?= date('Y-m-d') ?>" onchange="updateRef(this.value)">
      </div>
      <div>
        <label class="block text-xs font-bold text-on-surface-variant uppercase mb-2">Quantité Produite Estimée *</label>
        <div class="flex border border-outline-variant rounded-xl h-[50px] overflow-hidden">
          <button type="button" onclick="adj('qte',-1)" class="w-12 bg-surface-container-low border-r border-outline-variant flex items-center justify-center font-bold text-xl hover:bg-surface-variant transition-colors">−</button>
          <input type="number" name="quantite_kg" id="qte" value="10" min="0" step="0.5" class="flex-1 text-center font-black text-xl border-none focus:ring-0">
          <button type="button" onclick="adj('qte',1)" class="w-12 bg-surface-container-low border-l border-outline-variant flex items-center justify-center font-bold text-xl hover:bg-surface-variant transition-colors">+</button>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
      <div>
        <label class="block text-xs font-bold text-on-surface-variant uppercase mb-2">Conditionnement</label>
        <div class="flex border border-outline-variant rounded-xl overflow-hidden bg-surface-container-low">
          <label class="flex-1 cursor-pointer">
            <input type="radio" name="conditionnement" value="mise_sous_vide" class="hidden peer">
            <div class="py-3 text-center text-sm font-bold text-on-surface-variant peer-checked:bg-primary peer-checked:text-on-primary transition-colors">
              Mise sous vide
            </div>
          </label>
          <label class="flex-1 cursor-pointer">
            <input type="radio" name="conditionnement" value="barquette" checked class="hidden peer">
            <div class="py-3 text-center text-sm font-bold text-on-surface-variant peer-checked:bg-primary peer-checked:text-on-primary transition-colors">
              Barquette
            </div>
          </label>
        </div>
      </div>
      <div>
        <label class="block text-xs font-bold text-on-surface-variant uppercase mb-2 flex items-center justify-between">
          <span>Température de Conservation</span>
          <span class="material-symbols-outlined text-sm">ac_unit</span>
        </label>
        <div class="flex border border-outline-variant rounded-xl overflow-hidden bg-surface-container-low">
          <label class="flex-1 cursor-pointer">
            <input type="radio" name="temperature_conservation" value="frais" checked class="hidden peer">
            <div class="py-3 text-center text-sm font-bold text-on-surface-variant peer-checked:bg-on-surface-variant peer-checked:text-surface transition-colors">
              +3°C (Frais)
            </div>
          </label>
          <label class="flex-1 cursor-pointer">
            <input type="radio" name="temperature_conservation" value="surgele" class="hidden peer">
            <div class="py-3 text-center text-sm font-bold text-on-surface-variant peer-checked:bg-on-surface-variant peer-checked:text-surface transition-colors">
              -18°C (Surgelé)
            </div>
          </label>
        </div>
      </div>
    </div>
  </div>

  <!-- Section 2 : Lots Carcasse Viande -->
  <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 md:p-6 shadow-sm">
    <div class="flex justify-between items-center mb-2">
      <div class="text-sm font-bold text-on-surface flex items-center gap-2 uppercase tracking-wider">
        <span>🐑</span> Lots Carcasse (Viande Origine)
      </div>
      <button type="button" class="px-3 py-1 text-primary text-xs font-bold flex items-center gap-1 hover:underline transition-colors" onclick="alert('Scanner Lot : placez le code barre devant la caméra')">
        <span class="material-symbols-outlined text-sm">qr_code_scanner</span> Scanner Lot
      </button>
    </div>
    
    <div class="text-xs text-on-surface-variant mb-4">Vous pouvez lier plusieurs lots de carcasse à cette préparation.</div>

    <div class="flex gap-2 mb-4">
      <div class="relative flex-1">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant material-symbols-outlined">search</span>
        <input type="text" id="lotSearch" placeholder="Rechercher un lot de carcasse..." oninput="filtrerLots(this.value)" class="w-full pl-10 pr-4 py-3 border border-outline-variant rounded-xl bg-surface focus:border-primary focus:ring-1 focus:ring-primary outline-none">
      </div>
      <button type="button" class="px-6 py-3 bg-primary-container text-on-primary-container font-bold rounded-xl flex items-center gap-2 hover:opacity-90 transition-opacity whitespace-nowrap" onclick="toggleLotDropdown()">
        <span class="material-symbols-outlined text-sm">add</span> Ajouter
      </button>
    </div>

    <!-- Liste déroulante des lots disponibles pour sélection rapide -->
    <div id="lotListe" class="hidden max-h-[150px] overflow-y-auto border border-outline-variant rounded-xl bg-surface mb-4">
      <?php foreach ($lots_dispo as $l):
        $s = solde_lot($l['id']);
        if ($s <= 0) continue;
      ?>
        <div class="lot-item p-3 cursor-pointer border-l-4 border-b border-b-outline-variant last:border-b-0 flex justify-between items-center hover:bg-surface-container-low transition-colors"
             style="border-left-color: <?= h($l['couleur']) ?>"
             onclick="ajouterViande(<?= $l['id'] ?>, <?= h(json_encode($l['num_lot'])) ?>, <?= h(json_encode($l['libelle'])) ?>, <?= $s ?>, <?= h(json_encode($l['couleur'])) ?>, <?= h(json_encode($l['emoji'])) ?>, <?= h(json_encode($l['origine'])) ?>, <?= h(json_encode($l['code'])) ?>)"
             data-lot="<?= h(strtolower($l['num_lot'])) ?>">
          <div class="flex items-center gap-3">
            <span class="text-2xl"><?= $l['emoji'] ?></span>
            <div>
              <div class="font-bold text-sm text-on-surface"><?= h($l['libelle']) ?> (<?= h($l['num_lot']) ?>)</div>
              <div class="text-xs text-on-surface-variant">Origine: <?= h($l['origine']) ?></div>
            </div>
          </div>
          <span class="bg-primary-container text-on-primary-container text-xs font-bold px-2 py-1 rounded-full"><?= number_format($s, 1, ',', ' ') ?> kg</span>
        </div>
      <?php endforeach ?>
    </div>

    <!-- Viandes Sélectionnées -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div id="viandesSelected" class="contents"></div>
      
      <!-- Bouton d'ajout d'un autre lot -->
      <button type="button" id="btnAddLotPointille" onclick="toggleLotDropdown()" class="border-2 border-dashed border-outline-variant bg-surface-container-low text-on-surface-variant rounded-xl flex flex-col items-center justify-center min-h-[120px] hover:border-primary hover:text-primary hover:bg-surface-container-highest transition-colors">
        <span class="material-symbols-outlined text-2xl mb-1">add_circle</span>
        <span class="text-sm font-bold">Ajouter un autre lot</span>
      </button>
    </div>
  </div>

  <!-- Section 3 : Ingrédients & Épices (Secs) -->
  <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 md:p-6 shadow-sm">
    <div class="flex justify-between items-center mb-4">
      <div class="text-sm font-bold text-on-surface flex items-center gap-2 uppercase tracking-wider">
        <span>🌱</span> Ingrédients & Épices
      </div>
    </div>

    <div id="ingrSelected" class="flex flex-col gap-3 mb-4"></div>

    <button type="button" onclick="toggleIngrDropdown()" class="w-full py-4 border-2 border-dashed border-secondary text-secondary rounded-xl font-bold bg-secondary-container bg-opacity-20 hover:bg-opacity-40 transition-colors flex items-center justify-center gap-2">
      <span class="material-symbols-outlined text-sm">add</span> Ajouter un ingrédient
    </button>
    
    <div id="ingrPickerWrapper" class="hidden mt-4">
      <select id="ingrPicker" onchange="ingrPicked(this)" class="w-full px-4 py-3 border border-outline-variant rounded-xl bg-surface focus:border-primary outline-none">
        <option value="">-- Sélectionner un ingrédient --</option>
        <?php
        $cat='';
        foreach($ingredients as $i):
          if($i['categorie']!==$cat){ $cat=$i['categorie']; echo '<optgroup label="'.h($cat).'">'; }
        ?>
        <?php foreach($lignes_dispo as $l): if((int)$l['ingredient_id']===$i['id']): ?>
        <option value="<?= $l['id'] ?>"
                data-nom="<?= h($i['nom']) ?>"
                data-unite="<?= h($i['unite']) ?>"
                data-lot="<?= h($l['ref_lot_ing']??'') ?>"
                data-fac="<?= h($l['num_facture']??fmt_date($l['date_achat'])) ?>">
          <?= h($i['nom']) ?> (Lot: <?= h($l['ref_lot_ing']?:($l['num_facture']?:'—')) ?>)
        </option>
        <?php break; endif; endforeach ?>
        <?php endforeach ?>
      </select>
    </div>
  </div>

  <!-- Notes & Observations -->
  <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 md:p-6 shadow-sm">
    <label class="block text-xs font-bold text-on-surface-variant uppercase mb-2">Notes & Observations</label>
    <textarea name="notes" class="w-full px-4 py-3 border border-outline-variant rounded-xl bg-surface focus:border-primary outline-none min-h-[100px]" placeholder="Recette, observations..."></textarea>
  </div>

  <!-- Boutons Action -->
  <div class="grid grid-cols-2 gap-4 mb-8">
    <a href="traiteur.php?vue=plats" class="bg-surface-variant text-on-surface-variant font-bold py-4 rounded-xl text-center hover:bg-outline-variant transition-colors shadow-sm">
      Annuler
    </a>
    <button type="submit" name="save_plat" class="bg-primary text-on-primary font-bold py-4 rounded-xl hover:bg-primary-container active:scale-95 transition-all shadow-md flex items-center justify-center gap-2">
      <span class="material-symbols-outlined">check</span>
      Valider
    </button>
  </div>
</form>

<?php else:
// ════════════════════ LISTES PLATS & ACHATS ════════════════════
?>

<div class="flex bg-surface rounded-t-xl overflow-hidden border-b border-outline-variant mb-6">
  <a href="traiteur.php?vue=plats" class="flex-1 text-center py-4 text-sm font-bold transition-all border-b-2 <?= $vue==='plats'?'text-primary border-primary':'text-on-surface-variant border-transparent' ?>">
    ✂️ Plats cuisinés
  </a>
  <a href="traiteur.php?vue=achats" class="flex-1 text-center py-4 text-sm font-bold transition-all border-b-2 <?= $vue==='achats'?'text-primary border-primary':'text-on-surface-variant border-transparent' ?>">
    🧾 Achats Ingrédients
  </a>
</div>

<?php if ($vue==='achats'): ?>
  <!-- Liste des achats -->
  <div class="flex flex-col gap-4">
  <?php if ($achats): ?>
    <?php foreach ($achats as $a):
      $ligs=$pdo->prepare('SELECT al.*,i.nom,i.unite FROM achats_lignes al JOIN ingredients i ON i.id=al.ingredient_id WHERE al.achat_id=?');
      $ligs->execute([$a['id']]); $ligs=$ligs->fetchAll();
    ?>
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 shadow-sm">
      <div class="flex justify-between items-start mb-4">
        <div>
          <div class="font-extrabold text-on-surface">🧾 <?= $a['num_facture']?h($a['num_facture']):'Sans réf.' ?></div>
          <div class="text-xs text-on-surface-variant mt-1"><?= fmt_date($a['date_achat']) ?><?= $a['fournisseur']?' · '.h($a['fournisseur']):'' ?></div>
        </div>
        <div class="text-right flex flex-col items-end gap-2">
          <?php if ($a['montant_total']): ?>
          <span class="text-lg font-black text-secondary"><?= number_format($a['montant_total'],2,',',' ') ?> €</span>
          <?php endif ?>
          <?php if ($a['doc_facture']): ?>
          <a href="<?= UPLOAD_URL.h($a['doc_facture']) ?>" target="_blank" class="bg-surface-variant text-on-surface-variant px-2 py-1 rounded text-xs font-bold hover:bg-outline-variant transition-colors">📄 Fichier</a>
          <?php endif ?>
        </div>
      </div>
      <div class="border-t border-outline-variant">
      <?php foreach ($ligs as $l): ?>
      <div class="flex justify-between items-center py-2 border-b border-outline-variant last:border-b-0 text-sm">
        <div>
          <span class="font-bold text-on-surface"><?= h($l['nom']) ?></span>
          <?php if ($l['ref_lot_ing']): ?><span class="text-xs text-on-surface-variant ml-2">Lot: <?= h($l['ref_lot_ing']) ?></span><?php endif ?>
        </div>
        <div class="font-black text-secondary">
          <?= number_format($l['quantite'],3,',') ?> <?= h($l['unite']) ?>
          <?php if ($l['prix_unitaire']): ?><span class="font-normal text-xs text-on-surface-variant ml-1">· <?= number_format($l['prix_unitaire'],3,',') ?>€/u</span><?php endif ?>
        </div>
      </div>
      <?php endforeach ?>
      </div>
    </div>
    <?php endforeach ?>
  <?php else: ?>
    <div class="bg-surface-container-low text-primary p-4 rounded-xl border border-outline-variant">Aucun achat. Utilisez + pour enregistrer une facture.</div>
  <?php endif ?>
  </div>
  <!-- Floating Action Button for Achats (not implemented in this mock, but could point to an 'ajouter achat' action) -->
  <a href="traiteur.php?vue=achats&action=achat" class="fixed bottom-20 right-4 w-14 h-14 bg-primary text-on-primary rounded-full shadow-lg flex items-center justify-center hover:bg-primary-container transition-colors md:bottom-10 md:right-10 no-print z-40">
    <span class="material-symbols-outlined text-2xl">add</span>
  </a>

<?php else: ?>
  <!-- Liste des Plats -->
  <div class="flex flex-col gap-3">
  <?php if ($plats): ?>
    <?php foreach ($plats as $p): ?>
    <a href="traiteur.php?plat_id=<?= $p['id'] ?>" class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 flex items-center justify-between hover:bg-surface-container-low transition-colors shadow-sm cursor-pointer">
      <div class="flex items-center gap-4">
        <span class="text-2xl">✂️</span>
        <div>
          <div class="font-bold text-on-surface"><?= h($p['nom']) ?></div>
          <div class="text-xs text-on-surface-variant mt-1">
            <span class="font-bold text-secondary"><?= h($p['ref_lot_fini']??'—') ?></span> · <?= fmt_date($p['date_preparation']) ?>
            <?php if ($p['conditionnement']&&$p['conditionnement']!=='vrac'): ?>
             · <?= $p['conditionnement']==='mise_sous_vide'?'🫙 MSV':'📦 Barq.' ?>
            <?php endif ?>
          </div>
        </div>
      </div>
      <?php if ($p['quantite_kg']||$p['nb_portions']): ?>
      <div class="text-right">
        <?php if ($p['quantite_kg']): ?><div class="font-black text-primary"><?= number_format($p['quantite_kg'],1,',') ?> kg</div><?php endif ?>
        <?php if ($p['nb_portions']): ?><div class="text-xs text-on-surface-variant font-bold"><?= $p['nb_portions'] ?> port.</div><?php endif ?>
      </div>
      <?php endif ?>
    </a>
    <?php endforeach ?>
  <?php else: ?>
    <div class="bg-surface-container-low text-primary p-4 rounded-xl border border-outline-variant">Aucun plat. Utilisez + pour créer une préparation.</div>
  <?php endif ?>
  </div>
  <a href="traiteur.php?vue=plats&action=plat" class="fixed bottom-20 right-4 w-14 h-14 bg-primary text-on-primary rounded-full shadow-lg flex items-center justify-center hover:bg-primary-container transition-colors md:bottom-10 md:right-10 no-print z-40">
    <span class="material-symbols-outlined text-2xl">add</span>
  </a>
<?php endif ?>

<?php endif ?>

<!-- Scripts interactifs pour le formulaire plat -->
<script>
let viandeData = {};
let ingrData   = {};

function adj(id, d) {
  const el = document.getElementById(id);
  el.value = Math.max(0, parseFloat(el.value || 0) + d);
}

function updateRef(dateVal) {
  if (!dateVal) return;
  const d = dateVal.replace(/-/g, '');
  document.getElementById('refAuto').value = 'TR-' + d + '-001';
}

function regenRef() {
  const d = document.querySelector('[name=date_preparation]').value.replace(/-/g, '');
  const r = Math.floor(Math.random() * 900 + 100);
  document.getElementById('refAuto').value = 'TR-' + d + '-' + r;
}

function toggleLotDropdown() {
  const lotListe = document.getElementById('lotListe');
  lotListe.classList.toggle('hidden');
  if (!lotListe.classList.contains('hidden')) {
    document.getElementById('lotSearch').focus();
  }
}

function filtrerLots(v) {
  v = v.toLowerCase();
  document.querySelectorAll('#lotListe .lot-item').forEach(el => {
    el.style.display = (!v || el.dataset.lot.includes(v)) ? '' : 'none';
  });
  const lotListe = document.getElementById('lotListe');
  if (lotListe.classList.contains('hidden')) {
      lotListe.classList.remove('hidden');
  }
}

function ajouterViande(lid, num, libelle, solde, couleur, emoji, origine, code) {
  if (viandeData[lid]) return;
  viandeData[lid] = true;
  
  const div = document.createElement('div');
  div.id = 'vs_' + lid;
  div.className = 'relative bg-surface border-2 rounded-xl p-4 flex flex-col gap-3 shadow-sm';
  div.style.borderColor = couleur;
  
  div.innerHTML = `
    <button type="button" onclick="retirerViande(${lid})" class="absolute top-2 right-2 w-6 h-6 rounded-full bg-error-container text-error flex items-center justify-center font-bold hover:bg-error hover:text-on-error transition-colors">
      <span class="material-symbols-outlined" style="font-size: 14px">close</span>
    </button>
    
    <div class="flex items-center gap-3 pr-6">
      <div class="w-12 h-12 rounded-lg flex items-center justify-center text-3xl shadow-sm text-white" style="background:${couleur}">
        ${emoji}
      </div>
      <div>
        <div class="font-black text-sm text-on-surface">${code||libelle} - ${libelle}</div>
        <div class="text-xs text-on-surface-variant mt-1">Lot: ${num}</div>
      </div>
    </div>
    
    <div class="flex items-center justify-between mt-auto pt-3 border-t border-outline-variant">
      <span class="bg-surface-container-low border border-outline-variant text-on-surface-variant text-[10px] font-bold px-2 py-1 rounded">Origine: ${origine}</span>
      <div class="flex items-center gap-2">
        <input type="number" name="viande_poids[]" step="0.01" max="${solde}" required
               class="w-16 px-2 py-1 border border-outline-variant bg-surface-container-lowest rounded text-sm font-bold text-center focus:border-primary outline-none"
               placeholder="0.0">
        <span class="text-xs font-bold text-on-surface-variant">kg</span>
        <input type="hidden" name="viande_lot[]" value="${lid}">
      </div>
    </div>
  `;
  
  document.getElementById('viandesSelected').appendChild(div);
  document.getElementById('lotSearch').value = '';
  document.getElementById('lotListe').classList.add('hidden');
  filtrerLots('');
}

function retirerViande(lid) {
  delete viandeData[lid];
  const el = document.getElementById('vs_' + lid);
  if (el) el.remove();
}

function toggleIngrDropdown() {
  const wrapper = document.getElementById('ingrPickerWrapper');
  wrapper.classList.toggle('hidden');
}

function ingrPicked(sel) {
  const opt = sel.options[sel.selectedIndex];
  if (!opt.value) return;
  const lid = opt.value;
  if (ingrData[lid]) {
    sel.value = '';
    document.getElementById('ingrPickerWrapper').classList.add('hidden');
    return;
  }
  ingrData[lid] = true;
  
  const div = document.createElement('div');
  div.id = 'ig_' + lid;
  div.className = 'bg-surface-container-low border border-outline-variant rounded-xl p-3';
  
  const unit = opt.dataset.unite;
  const lotInfo = opt.dataset.lot ? `Lot: ${opt.dataset.lot}` : `Facture: ${opt.dataset.fac}`;
  
  div.innerHTML = `
    <div class="flex justify-between items-start mb-2">
      <div>
        <div class="font-bold text-sm text-on-surface">${opt.dataset.nom}</div>
        <div class="text-xs text-on-surface-variant italic mt-0.5">${lotInfo}</div>
      </div>
      <button type="button" onclick="retirerIngr('${lid}')" class="px-2 py-1 rounded bg-error-container text-error font-bold text-xs hover:bg-error hover:text-on-error transition-colors">
        ✕
      </button>
    </div>
    <div class="flex items-center gap-2 mt-2">
      <input type="number" name="ingr_qte[]" step="${unit==='g'?'1':'0.001'}" min="0" required
             class="flex-1 px-3 py-2 border border-outline-variant rounded-lg text-sm font-bold focus:border-primary outline-none"
             placeholder="Quantité">
      <select name="ingr_unit[]" class="px-3 py-2 border border-outline-variant rounded-lg text-sm font-bold bg-surface focus:border-primary outline-none">
        <option value="${unit}" selected>${unit}</option>
        <option value="${unit==='g'?'kg':'g'}">${unit==='g'?'kg':'g'}</option>
      </select>
      <input type="hidden" name="ingr_ligne[]" value="${lid}">
    </div>
  `;
  
  document.getElementById('ingrSelected').appendChild(div);
  sel.value = '';
  document.getElementById('ingrPickerWrapper').classList.add('hidden');
}

function retirerIngr(lid) {
  delete ingrData[lid];
  const el = document.getElementById('ig_' + lid);
  if (el) el.remove();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

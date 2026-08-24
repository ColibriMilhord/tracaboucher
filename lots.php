<?php
$page_active = 'carcasse';
$page_title  = 'Entrée Carcasse';
require_once __DIR__ . '/includes/db.php';

$pdo = db();
$msg = ''; $msg_type = 'ok';
$action = $_GET['action'] ?? '';
$lot_id = (int)($_GET['id'] ?? 0);

// Rediriger sans action vers la saisie simplifiée
if (empty($action) && $lot_id === 0) {
    header('Location: lots.php?action=ajouter');
    exit;
}

// ── POST : Enregistrer nouveau lot (Saisie simplifiée)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lot'])) {
    $espece_id = (int)$_POST['espece_id'];
    $date_e    = trim($_POST['date_entree']);
    $poids     = (float)str_replace(',', '.', $_POST['poids_carcasse_kg']);
    $num_aba   = trim($_POST['num_abattoir']);
    
    // Nouveaux champs optionnels
    $temp      = $_POST['temperature'] !== '' ? (float)str_replace(',', '.', $_POST['temperature']) : null;
    $qual      = trim($_POST['qualite']);
    $trac      = trim($_POST['tracabilite_status']);
    
    // Champs optionnels avancés
    $fourn     = trim($_POST['fournisseur']);
    $origine   = trim($_POST['origine'] ?: 'France');
    $notes     = trim($_POST['notes']);
    $nb        = max(1, (int)($_POST['nb_carcasses'] ?? 1));

    $doc_path = null;
    if (!empty($_FILES['doc_abattoir']['name'])) {
        $doc_path = upload_fichier($_FILES['doc_abattoir'], 'abattoir');
    }

    if ($espece_id && $poids > 0) {
        $num_lot = generer_num_lot($espece_id, $date_e);
        
        // Auto-certification si document joint
        if (empty($trac) && $doc_path) {
            $trac = 'CERTIFIÉ';
        }
        
        try {
            $pdo->prepare(
                'INSERT INTO lots_carcasses
                 (num_lot, espece_id, date_entree, nb_carcasses, poids_carcasse_kg, temperature, qualite, tracabilite_status, fournisseur, origine, num_abattoir, doc_abattoir, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $num_lot, $espece_id, $date_e, $nb, $poids, $temp, $qual ?: null, $trac ?: null,
                $fourn ?: null, $origine, $num_aba ?: null, $doc_path, $notes ?: null
            ]);
            header('Location: stocks.php?tab=carcasses&msg=add_ok');
            exit;
        } catch (Exception $e) {
            $msg = 'Erreur d\'enregistrement : ' . $e->getMessage(); $msg_type = 'err';
        }
    } else {
        $msg = 'Données incomplètes (espèce et poids requis).'; $msg_type = 'err';
    }
}

// ── POST : Mettre à jour un lot existant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_lot'])) {
    $espece_id = (int)$_POST['espece_id'];
    $poids     = (float)str_replace(',', '.', $_POST['poids_carcasse_kg']);
    
    // Nouveaux champs optionnels
    $temp      = $_POST['temperature'] !== '' ? (float)str_replace(',', '.', $_POST['temperature']) : null;
    $qual      = trim($_POST['qualite']);
    $trac      = trim($_POST['tracabilite_status']);
    
    // Champs optionnels avancés
    $fourn     = trim($_POST['fournisseur']);
    $origine   = trim($_POST['origine'] ?: 'France');
    $notes     = trim($_POST['notes']);

    // Récupérer l'ancien document
    $q_doc = $pdo->prepare('SELECT doc_abattoir FROM lots_carcasses WHERE id=?');
    $q_doc->execute([$lot_id]);
    $doc_path = $q_doc->fetchColumn();

    if (!empty($_FILES['doc_abattoir']['name'])) {
        $new_doc = upload_fichier($_FILES['doc_abattoir'], 'abattoir');
        if ($new_doc) {
            $doc_path = $new_doc;
        }
    }

    if ($lot_id > 0 && $espece_id && $poids > 0) {
        if (empty($trac) && $doc_path) {
            $trac = 'CERTIFIÉ';
        }
        
        try {
            $pdo->prepare(
                'UPDATE lots_carcasses
                 SET espece_id=?, poids_carcasse_kg=?, temperature=?, qualite=?, tracabilite_status=?, fournisseur=?, origine=?, doc_abattoir=?, notes=?
                 WHERE id=?'
            )->execute([
                $espece_id, $poids, $temp, $qual ?: null, $trac ?: null, $fourn ?: null, $origine, $doc_path, $notes ?: null, $lot_id
            ]);
            header('Location: stocks.php?tab=carcasses&msg=update_ok');
            exit;
        } catch (Exception $e) {
            $msg = 'Erreur de mise à jour : ' . $e->getMessage(); $msg_type = 'err';
        }
    } else {
        $msg = 'Données incomplètes.'; $msg_type = 'err';
    }
}

// Récupération des données en cas de modification
$lot = null;
if ($action === 'modifier' && $lot_id > 0) {
    $q = $pdo->prepare('SELECT * FROM lots_carcasses WHERE id = ?');
    $q->execute([$lot_id]);
    $lot = $q->fetch();
    if (!$lot) {
        header('Location: stocks.php');
        exit;
    }
    $page_title = 'Mise à jour Carcasse';
}

$especes = $pdo->query('SELECT * FROM especes ORDER BY libelle')->fetchAll();
$page_title_top = 'BoucherTraçabilité';

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?>
<div class="<?= $msg_type === 'ok' ? 'bg-primary-container text-on-primary-container' : 'bg-error-container text-error' ?> p-4 rounded-xl mb-4 font-bold border <?= $msg_type === 'ok' ? 'border-primary' : 'border-error' ?>">
  <?= h($msg) ?>
</div>
<?php endif ?>

<?php if ($action === 'ajouter'): ?>
  <!-- ════════════════════ PAGE ENTREE CARCASSE (AJOUTER) ════════════════════ -->
  <header class="mb-6">
    <h2 class="font-display text-2xl font-extrabold text-primary">Nouvelle Carcasse</h2>
    <p class="font-label-md text-on-surface-variant uppercase tracking-wide mt-1">Entrée & Traçabilité</p>
  </header>

  <form method="POST" enctype="multipart/form-data" action="lots.php?action=ajouter" class="flex flex-col gap-6">
    
    <!-- ÉTAPE 1 : Sélection de l'espèce -->
    <div class="mb-4">
      <div class="flex items-center gap-3 mb-3">
        <div class="w-8 h-8 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold">1</div>
        <div class="font-label-xl font-bold">Sélection de l'espèce</div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <?php foreach ($especes as $e): ?>
          <label class="cursor-pointer">
            <input type="radio" name="espece_id" value="<?= $e['id'] ?>" required class="hidden peer"
                   onchange="selectSpecies(this, '<?= $e['id'] ?>')">
            <div id="eb_<?= $e['id'] ?>" class="border-2 border-outline-variant rounded-xl p-4 text-center flex flex-col items-center gap-2 bg-surface transition-colors peer-checked:border-primary peer-checked:bg-surface-container-low peer-checked:text-primary">
              <span class="text-3xl"><?= $e['emoji'] ?></span>
              <span class="font-bold text-sm"><?= h($e['libelle']) ?></span>
            </div>
          </label>
        <?php endforeach ?>
      </div>
    </div>

    <!-- ÉTAPE 2 : Date d'entrée -->
    <div class="mb-4">
      <div class="flex items-center gap-3 mb-3">
        <div class="w-8 h-8 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold">2</div>
        <div class="font-label-xl font-bold">Date d'entrée</div>
      </div>
      <div class="relative mt-2">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant material-symbols-outlined">calendar_today</span>
        <input type="date" name="date_entree" class="w-full pl-10 pr-3 py-3 border border-outline-variant rounded-xl bg-surface focus:border-primary focus:ring-1 focus:ring-primary" required value="<?= date('Y-m-d') ?>">
      </div>
    </div>

    <!-- ÉTAPE 3 : Numéro de lot abattoir -->
    <div class="mb-4">
      <div class="flex items-center gap-3 mb-3">
        <div class="w-8 h-8 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold">3</div>
        <div class="font-label-xl font-bold">Numéro de lot abattoir</div>
      </div>
      <div class="relative mt-2">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant font-black">#</span>
        <input type="text" name="num_abattoir" class="w-full pl-10 pr-3 py-3 border border-outline-variant rounded-xl bg-surface focus:border-primary focus:ring-1 focus:ring-primary font-bold text-primary" placeholder="ex: L-2605-C7S">
      </div>
    </div>

    <!-- ÉTAPE 4 : Poids de la carcasse -->
    <div class="mb-4">
      <div class="flex items-center gap-3 mb-3">
        <div class="w-8 h-8 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold">4</div>
        <div class="font-label-xl font-bold">Poids de la carcasse</div>
      </div>
      <div class="relative mt-2">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant material-symbols-outlined">scale</span>
        <input type="number" name="poids_carcasse_kg" step="0.01" min="0.01" required class="w-full pl-10 pr-10 py-3 border border-outline-variant rounded-xl bg-surface focus:border-primary focus:ring-1 focus:ring-primary" placeholder="Poids en kg">
        <span class="absolute right-4 top-1/2 -translate-y-1/2 font-bold text-on-surface-variant">kg</span>
      </div>
    </div>

    <!-- ÉTAPE 5 : Traçabilité (Scanner Document) -->
    <div class="mb-4">
      <div class="flex items-center gap-3 mb-3">
        <div class="w-8 h-8 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold">5</div>
        <div class="font-label-xl font-bold">Traçabilité</div>
      </div>
      
      <label id="scanZone" class="border-2 border-dashed border-primary rounded-xl bg-surface-container-low p-6 text-center flex flex-col items-center justify-center gap-2 cursor-pointer mt-2 hover:bg-surface-variant transition-colors">
        <input type="file" name="doc_abattoir" accept="image/*,application/pdf" capture="environment" onchange="fileScanned(this)" class="hidden">
        <div class="bg-primary text-on-primary w-12 h-12 rounded-xl flex items-center justify-center">
          <span class="material-symbols-outlined">document_scanner</span>
        </div>
        <div id="scanTxt" class="font-bold text-primary">Scanner Document Abattoir</div>
        <div class="text-xs text-on-surface-variant">DAB ou Passeport animal</div>
      </label>
    </div>

    <!-- OPTIONS DE TRACABILITE SUPPLEMENTAIRES -->
    <details class="bg-surface border border-outline-variant rounded-xl p-4 mt-2">
      <summary class="font-bold text-sm text-on-surface cursor-pointer list-none flex justify-between items-center outline-none">
        <span class="flex items-center gap-2"><span class="material-symbols-outlined text-secondary">settings</span> Informations supplémentaires (Optionnel)</span>
        <span class="text-xs">▼</span>
      </summary>
      <div class="mt-4 flex flex-col gap-4">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-bold text-on-surface-variant uppercase mb-1">Température (°C)</label>
            <input type="number" name="temperature" step="0.1" class="w-full px-3 py-2 border border-outline-variant rounded-lg focus:border-primary outline-none" placeholder="ex: 2.4">
          </div>
          <div>
            <label class="block text-xs font-bold text-on-surface-variant uppercase mb-1">Qualité</label>
            <input type="text" name="qualite" class="w-full px-3 py-2 border border-outline-variant rounded-lg focus:border-primary outline-none" placeholder="ex: A++">
          </div>
        </div>
        <div>
          <label class="block text-xs font-bold text-on-surface-variant uppercase mb-1">Statut Traçabilité</label>
          <input type="text" name="tracabilite_status" class="w-full px-3 py-2 border border-outline-variant rounded-lg focus:border-primary outline-none" placeholder="ex: CERTIFIÉ">
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-bold text-on-surface-variant uppercase mb-1">Fournisseur / Éleveur</label>
            <input type="text" name="fournisseur" class="w-full px-3 py-2 border border-outline-variant rounded-lg focus:border-primary outline-none" placeholder="ex: Charolais">
          </div>
          <div>
            <label class="block text-xs font-bold text-on-surface-variant uppercase mb-1">Origine</label>
            <input type="text" name="origine" class="w-full px-3 py-2 border border-outline-variant rounded-lg focus:border-primary outline-none" value="France">
          </div>
        </div>
        <div>
          <label class="block text-xs font-bold text-on-surface-variant uppercase mb-1">Nombre de carcasses</label>
          <input type="number" name="nb_carcasses" value="1" min="1" class="w-full px-3 py-2 border border-outline-variant rounded-lg focus:border-primary outline-none">
        </div>
        <div>
          <label class="block text-xs font-bold text-on-surface-variant uppercase mb-1">Notes / Observations</label>
          <textarea name="notes" class="w-full px-3 py-2 border border-outline-variant rounded-lg focus:border-primary outline-none min-h-[80px]" placeholder="Observations..."></textarea>
        </div>
      </div>
    </details>

    <button type="submit" name="save_lot" class="bg-primary text-on-primary font-bold py-4 rounded-xl hover:bg-primary-container active:scale-95 transition-all text-lg mt-4 shadow-md w-full">
      Valider l'entrée
    </button>
  </form>

<?php elseif ($action === 'modifier' && $lot): ?>
  <!-- ════════════════════ PAGE MISE A JOUR CARCASSE (MODIFIER) ════════════════════ -->
  
  <div class="bg-surface-container-high border border-outline-variant rounded-xl p-4 text-center mb-6">
    <div class="text-xs font-bold text-secondary uppercase tracking-wide">Lot Abattoir Généré</div>
    <div class="text-2xl font-extrabold text-on-surface mt-1"># <?= h($lot['num_lot']) ?></div>
  </div>

  <form method="POST" enctype="multipart/form-data" action="lots.php?action=modifier&id=<?= $lot['id'] ?>" class="flex flex-col gap-6">
    <input type="hidden" name="lot_id" value="<?= $lot['id'] ?>">

    <!-- ÉTAPE 1 : Sélection de l'espèce -->
    <div class="mb-4">
      <div class="flex items-center gap-3 mb-3">
        <div class="w-8 h-8 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold">1</div>
        <div class="font-label-xl font-bold">Sélection de l'espèce</div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <?php foreach ($especes as $e):
          $selected = ($lot['espece_id'] == $e['id']);
        ?>
          <label class="cursor-pointer">
            <input type="radio" name="espece_id" value="<?= $e['id'] ?>" required <?= $selected?'checked':'' ?> class="hidden peer"
                   onchange="selectSpecies(this, '<?= $e['id'] ?>')">
            <div id="eb_<?= $e['id'] ?>" class="border-2 <?= $selected ? 'border-primary bg-surface-container-low text-primary' : 'border-outline-variant bg-surface text-on-surface' ?> rounded-xl p-4 text-center flex flex-col items-center gap-2 transition-colors peer-checked:border-primary peer-checked:bg-surface-container-low peer-checked:text-primary">
              <span class="text-3xl"><?= $e['emoji'] ?></span>
              <span class="font-bold text-sm"><?= h($e['libelle']) ?></span>
            </div>
          </label>
        <?php endforeach ?>
      </div>
    </div>

    <!-- ÉTAPE 2 : Poids de la carcasse -->
    <div class="mb-4">
      <div class="flex items-center gap-3 mb-3">
        <div class="w-8 h-8 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold">2</div>
        <div class="font-label-xl font-bold">Poids de la carcasse</div>
      </div>
      <div class="relative mt-2">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant material-symbols-outlined">scale</span>
        <input type="number" name="poids_carcasse_kg" step="0.01" min="0.01" required value="<?= h($lot['poids_carcasse_kg']) ?>" class="w-full pl-10 pr-10 py-3 border border-outline-variant rounded-xl bg-surface focus:border-primary focus:ring-1 focus:ring-primary" placeholder="Poids en kg">
        <span class="absolute right-4 top-1/2 -translate-y-1/2 font-bold text-on-surface-variant">kg</span>
      </div>
    </div>

    <!-- ÉTAPE 3 : Traçabilité (Scanner Document) -->
    <div class="mb-4">
      <div class="flex items-center gap-3 mb-3">
        <div class="w-8 h-8 rounded-full bg-primary text-on-primary flex items-center justify-center font-bold">3</div>
        <div class="font-label-xl font-bold">Traçabilité</div>
      </div>
      
      <label id="scanZone" class="border-2 border-dashed <?= $lot['doc_abattoir'] ? 'border-[#2c5530] bg-[#f9f8f4]' : 'border-primary bg-surface-container-low' ?> rounded-xl p-6 text-center flex flex-col items-center justify-center gap-2 cursor-pointer mt-2 hover:bg-surface-variant transition-colors">
        <input type="file" name="doc_abattoir" accept="image/*,application/pdf" capture="environment" onchange="fileScanned(this)" class="hidden">
        <div class="bg-primary text-on-primary w-12 h-12 rounded-xl flex items-center justify-center">
          <span class="material-symbols-outlined">document_scanner</span>
        </div>
        <div id="scanTxt" class="font-bold text-primary">
          <?= $lot['doc_abattoir'] ? '✅ Document Chargé' : 'Scanner Document Abattoir' ?>
        </div>
        <div class="text-xs text-on-surface-variant">DAB ou Passeport animal</div>
      </label>
    </div>

    <!-- OPTIONS DE TRACABILITE SUPPLEMENTAIRES -->
    <details class="bg-surface border border-outline-variant rounded-xl p-4 mt-2">
      <summary class="font-bold text-sm text-on-surface cursor-pointer list-none flex justify-between items-center outline-none">
        <span class="flex items-center gap-2"><span class="material-symbols-outlined text-secondary">settings</span> Informations supplémentaires (Optionnel)</span>
        <span class="text-xs">▼</span>
      </summary>
      <div class="mt-4 flex flex-col gap-4">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-bold text-on-surface-variant uppercase mb-1">Température (°C)</label>
            <input type="number" name="temperature" step="0.1" class="w-full px-3 py-2 border border-outline-variant rounded-lg focus:border-primary outline-none" value="<?= h($lot['temperature'] ?? '') ?>" placeholder="ex: 2.4">
          </div>
          <div>
            <label class="block text-xs font-bold text-on-surface-variant uppercase mb-1">Qualité</label>
            <input type="text" name="qualite" class="w-full px-3 py-2 border border-outline-variant rounded-lg focus:border-primary outline-none" value="<?= h($lot['qualite'] ?? '') ?>" placeholder="ex: A++">
          </div>
        </div>
        <div>
          <label class="block text-xs font-bold text-on-surface-variant uppercase mb-1">Statut Traçabilité</label>
          <input type="text" name="tracabilite_status" class="w-full px-3 py-2 border border-outline-variant rounded-lg focus:border-primary outline-none" value="<?= h($lot['tracabilite_status'] ?? '') ?>" placeholder="ex: CERTIFIÉ">
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-bold text-on-surface-variant uppercase mb-1">Fournisseur</label>
            <input type="text" name="fournisseur" class="w-full px-3 py-2 border border-outline-variant rounded-lg focus:border-primary outline-none" value="<?= h($lot['fournisseur'] ?? '') ?>" placeholder="ex: Charolais">
          </div>
          <div>
            <label class="block text-xs font-bold text-on-surface-variant uppercase mb-1">Origine</label>
            <input type="text" name="origine" class="w-full px-3 py-2 border border-outline-variant rounded-lg focus:border-primary outline-none" value="<?= h($lot['origine'] ?? 'France') ?>">
          </div>
        </div>
        <div>
          <label class="block text-xs font-bold text-on-surface-variant uppercase mb-1">Notes / Observations</label>
          <textarea name="notes" class="w-full px-3 py-2 border border-outline-variant rounded-lg focus:border-primary outline-none min-h-[80px]" placeholder="Observations..."><?= h($lot['notes'] ?? '') ?></textarea>
        </div>
      </div>
    </details>

    <div class="grid grid-cols-2 gap-4 mt-4">
      <a href="stocks.php" class="bg-surface-variant text-on-surface-variant font-bold py-4 rounded-xl text-center hover:bg-outline-variant transition-colors">Annuler</a>
      <button type="submit" name="update_lot" class="bg-primary text-on-primary font-bold py-4 rounded-xl hover:bg-primary-container active:scale-95 transition-all shadow-md w-full">
        Valider
      </button>
    </div>

  </form>
<?php endif ?>

<script>
function selectSpecies(radio, id) {
  // L'apparence est gérée par Tailwind "peer-checked", mais pour le fallback JS :
  // Normalement pas besoin de JS pour les classes CSS ici grace à peer/peer-checked
}

function fileScanned(input) {
  const scanTxt = document.getElementById('scanTxt');
  const scanZone = document.getElementById('scanZone');
  if (input.files.length > 0) {
    scanTxt.textContent = '✅ ' + input.files[0].name;
    // scanZone.classList.add('border-[#2c5530]', 'bg-[#f9f8f4]');
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

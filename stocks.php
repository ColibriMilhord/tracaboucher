<?php
$page_active = 'stocks';
$page_title  = 'Gestion des Stocks';
$page_title_top = 'Gestion des Stocks';
require_once __DIR__ . '/includes/db.php';

$pdo = db();
$msg = ''; $msg_type = 'ok';

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'add_ok') {
        $msg = '✅ Lot de carcasse enregistré avec succès !';
    } elseif ($_GET['msg'] === 'update_ok') {
        $msg = '✅ Lot de carcasse mis à jour avec succès !';
    }
}

// ── Traitement de la suppression d'un lot carcasse
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_lot'])) {
    $del_lot_id = (int)$_POST['lot_id'];
    if ($del_lot_id > 0) {
        $pdo->beginTransaction();
        try {
            // Supprimer les viandes associées dans les plats cuisinés
            $pdo->prepare('DELETE FROM plats_viande WHERE lot_id = ?')->execute([$del_lot_id]);
            // Supprimer le lot de carcasse
            $pdo->prepare('DELETE FROM lots_carcasses WHERE id = ?')->execute([$del_lot_id]);
            $pdo->commit();
            $msg = "✅ Le lot de carcasse a été supprimé avec succès."; $msg_type = 'ok';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "❌ Erreur lors de la suppression : " . $e->getMessage(); $msg_type = 'err';
        }
    }
}

// ── Traitement de la suppression d'une sortie viande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_sortie'])) {
    $del_sortie_id = (int)$_POST['sortie_id'];
    if ($del_sortie_id > 0) {
        try {
            $pdo->prepare('DELETE FROM sorties_viande WHERE id = ?')->execute([$del_sortie_id]);
            $msg = "✅ La sortie a été supprimée."; $msg_type = 'ok';
        } catch (Exception $e) {
            $msg = "❌ Erreur : " . $e->getMessage(); $msg_type = 'err';
        }
    }
}

$tab = $_GET['tab'] ?? 'carcasses';
if (!in_array($tab, ['carcasses', 'sorties'])) {
    $tab = 'carcasses';
}

// Récupération des lots carcasses
$lots = $pdo->query(
    'SELECT l.*, e.code, e.libelle, e.emoji, e.couleur
     FROM lots_carcasses l
     JOIN especes e ON e.id = l.espece_id
     ORDER BY l.date_entree DESC, l.id DESC'
)->fetchAll();

// Récupération des sorties
$sorties = $pdo->query(
    'SELECT s.*, l.num_lot, e.code, e.libelle, e.emoji, e.couleur
     FROM sorties_viande s
     JOIN lots_carcasses l ON l.id = s.lot_id
     JOIN especes e ON e.id = l.espece_id
     ORDER BY s.date_sortie DESC, s.id DESC'
)->fetchAll();

// Formatteur de date en français
function fmt_date_fr(?string $d): string {
    if (!$d) return '—';
    $months = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'];
    $time = strtotime($d);
    $day = date('j', $time);
    $month = $months[(int)date('n', $time) - 1];
    $year = date('Y', $time);
    return "$day $month $year";
}

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?>
<div class="<?= $msg_type === 'ok' ? 'bg-primary-container text-on-primary-container border-primary' : 'bg-error-container text-error border-error' ?> p-4 rounded-xl mb-4 font-bold border">
  <?= h($msg) ?>
</div>
<?php endif ?>

<!-- Onglets Segmentés -->
<div class="flex bg-surface rounded-t-xl overflow-hidden border-b border-outline-variant mb-6">
  <a href="stocks.php?tab=carcasses" class="flex-1 text-center py-4 text-sm font-bold transition-all border-b-2 <?= $tab==='carcasses'?'text-primary border-primary':'text-on-surface-variant border-transparent' ?>">
    Lots Carcasses
  </a>
  <a href="stocks.php?tab=sorties" class="flex-1 text-center py-4 text-sm font-bold transition-all border-b-2 <?= $tab==='sorties'?'text-primary border-primary':'text-on-surface-variant border-transparent' ?>">
    Sorties Produits
  </a>
</div>

<!-- Barre de Recherche & Actions -->
<div class="relative flex flex-wrap gap-3 items-center mb-6 no-print">
  <div class="relative flex-1 min-w-[200px]">
    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant material-symbols-outlined">search</span>
    <input type="text" id="srchStocks" placeholder="Rechercher un lot..." oninput="filtrerStocks(this.value)" class="w-full pl-10 pr-4 py-3 border border-outline-variant rounded-xl bg-surface focus:border-primary focus:ring-1 focus:ring-primary outline-none">
  </div>
  
  <button onclick="toggleDatePicker()" class="px-4 py-3 border border-outline-variant rounded-xl bg-surface text-on-surface flex items-center gap-2 font-bold hover:bg-surface-container-low transition-colors">
    <span class="material-symbols-outlined text-sm">calendar_today</span> Date
  </button>
  <input type="date" id="dateFilter" onchange="filtrerDate(this.value)" class="hidden px-3 py-3 rounded-xl border border-outline-variant bg-surface outline-none">

  <a href="lots.php?action=ajouter" class="px-4 py-3 bg-primary text-on-primary font-bold rounded-xl flex items-center gap-2 hover:bg-primary-container transition-colors shadow-sm">
    <span class="material-symbols-outlined text-sm">add</span> Nouveau
  </a>
</div>

<!-- Contenu Onglet 1 : Lots Carcasses -->
<?php if ($tab === 'carcasses'): ?>
  <div id="lotsContainer" class="flex flex-col gap-4">
    <?php if ($lots): ?>
      <?php foreach ($lots as $l):
        $s = solde_lot($l['id']);
        $search_str = strtolower($l['num_lot'] . ' ' . $l['libelle'] . ' ' . ($l['fournisseur'] ?? '') . ' ' . ($l['num_abattoir'] ?? '') . ' ' . $l['date_entree']);
      ?>
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 md:p-6 stock-lot-card relative shadow-sm" data-s="<?= h($search_str) ?>" data-date="<?= h($l['date_entree']) ?>">
          
          <!-- Badge Espèce & Date de réception -->
          <div class="flex justify-between items-center mb-4">
            <span class="px-3 py-1 rounded-full text-xs font-black uppercase flex items-center gap-1 border" style="background:<?= h($l['couleur']) ?>15; color:<?= h($l['couleur']) ?>; border-color:<?= h($l['couleur']) ?>50;">
              <?= $l['emoji'] ?> <?= h($l['libelle']) ?><?= $l['fournisseur'] ? ' - ' . h($l['fournisseur']) : '' ?>
            </span>
            <div class="flex items-center gap-3">
              <span class="text-xs font-bold text-on-surface-variant uppercase">
                Reçu le <?= fmt_date_fr($l['date_entree']) ?>
              </span>
              <!-- Prévisualisation document abattoir -->
              <?php if ($l['doc_abattoir']): ?>
                <?php $ext = strtolower(pathinfo($l['doc_abattoir'], PATHINFO_EXTENSION)); ?>
                <a href="<?= UPLOAD_URL.h($l['doc_abattoir']) ?>" target="_blank" class="w-8 h-10 bg-surface-container-low border border-outline-variant rounded flex items-center justify-center overflow-hidden hover:opacity-80 transition-opacity" title="Voir le document">
                  <?php if (in_array($ext, ['jpg','jpeg','png','webp'])): ?>
                    <img src="<?= UPLOAD_URL.h($l['doc_abattoir']) ?>" class="w-full h-full object-cover">
                  <?php else: ?>
                    <span class="text-xs material-symbols-outlined">description</span>
                  <?php endif ?>
                </a>
              <?php endif ?>
            </div>
          </div>

          <!-- Titre du lot -->
          <div class="text-xl font-extrabold text-on-surface mb-4">
            Lot #<?= h($l['num_lot']) ?>
          </div>

          <!-- Grille Métadonnées -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
            <div class="bg-surface-container-low rounded-lg p-3 border border-outline-variant">
              <div class="text-[10px] text-on-surface-variant uppercase font-bold tracking-wider">Poids Carcasse</div>
              <div class="text-lg font-black text-secondary mt-1">
                <?= number_format($l['poids_carcasse_kg'], 2, ',', ' ') ?> <span class="text-xs font-medium text-on-surface-variant">kg</span>
              </div>
            </div>
            
            <div class="bg-surface-container-low rounded-lg p-3 border border-outline-variant">
              <?php if ($l['temperature'] !== null && $l['temperature'] > 0): ?>
                <div class="text-[10px] text-on-surface-variant uppercase font-bold tracking-wider">Température</div>
                <div class="text-lg font-black text-on-surface mt-1">
                  <?= number_format($l['temperature'], 1, ',', ' ') ?> <span class="text-xs font-medium text-on-surface-variant">°C</span>
                </div>
              <?php elseif ($l['qualite'] !== null && $l['qualite'] !== ''): ?>
                <div class="text-[10px] text-on-surface-variant uppercase font-bold tracking-wider">Qualité</div>
                <div class="text-lg font-black text-on-surface mt-1">
                  <?= h($l['qualite']) ?>
                </div>
              <?php elseif ($l['tracabilite_status'] !== null && $l['tracabilite_status'] !== ''): ?>
                <div class="text-[10px] text-on-surface-variant uppercase font-bold tracking-wider">Traçabilité</div>
                <div class="text-lg font-black text-primary mt-1">
                  <?= h($l['tracabilite_status']) ?>
                </div>
              <?php else: ?>
                <div class="text-[10px] text-on-surface-variant uppercase font-bold tracking-wider">Traçabilité</div>
                <div class="text-lg font-black text-primary mt-1">
                  CERTIFIÉ
                </div>
              <?php endif ?>
            </div>
          </div>

          <!-- Progrès Stock restant -->
          <?php $pct = $l['poids_carcasse_kg'] > 0 ? (($l['poids_carcasse_kg'] - $s) / $l['poids_carcasse_kg']) * 100 : 0; ?>
          <div class="flex justify-between text-xs text-on-surface-variant mb-1">
            <span>Restant : <strong class="text-on-surface"><?= number_format($s, 2, ',', ' ') ?> kg</strong></span>
            <span><?= 100 - round($pct) ?>% dispo</span>
          </div>
          <div class="w-full bg-surface-variant rounded-full h-2 mb-4">
            <div class="<?= $pct >= 95 ? 'bg-error' : ($pct >= 70 ? 'bg-secondary' : 'bg-primary') ?> h-2 rounded-full" style="width:<?= 100 - $pct ?>%"></div>
          </div>

          <!-- Actions -->
          <div class="flex gap-3 no-print">
            <a href="lots.php?action=modifier&id=<?= $l['id'] ?>" class="flex-1 bg-surface-container-high text-on-surface font-bold rounded-xl py-3 flex items-center justify-center gap-2 hover:bg-outline-variant transition-colors shadow-sm border border-outline-variant">
              <span class="material-symbols-outlined text-sm">edit</span> Modifier
            </a>
            
            <form method="POST" onsubmit="return confirm('Voulez-vous vraiment supprimer ce lot de carcasse ainsi que toutes ses sorties associées ?')" class="inline">
              <input type="hidden" name="lot_id" value="<?= $l['id'] ?>">
              <button type="submit" name="delete_lot" class="bg-error-container text-error rounded-xl p-3 flex items-center justify-center hover:bg-error hover:text-on-error transition-colors shadow-sm">
                <span class="material-symbols-outlined">delete</span>
              </button>
            </form>
          </div>

        </div>
      <?php endforeach ?>
    <?php else: ?>
      <div class="bg-surface-container-low text-primary p-4 rounded-xl border border-outline-variant">Aucun lot de carcasse enregistré. <a href="lots.php?action=ajouter" class="font-bold underline">Enregistrer une carcasse →</a></div>
    <?php endif ?>
  </div>
<?php endif ?>

<!-- Contenu Onglet 2 : Sorties Produits -->
<?php if ($tab === 'sorties'): ?>
  <div id="sortiesContainer" class="flex flex-col gap-4">
    <?php if ($sorties): ?>
      <?php foreach ($sorties as $s):
        $search_str = strtolower($s['num_lot'] . ' ' . $s['libelle'] . ' ' . ($s['client'] ?? '') . ' ' . $s['type_sortie'] . ' ' . $s['date_sortie']);
        $badge_color = $s['type_sortie'] === 'vente_directe' ? '#3B82F6' : ($s['type_sortie'] === 'traiteur' ? '#8B5CF6' : '#6B7280');
      ?>
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 md:p-6 stock-sortie-card shadow-sm" data-s="<?= h($search_str) ?>" data-date="<?= h($s['date_sortie']) ?>">
          
          <div class="flex justify-between items-center mb-4">
            <span class="px-3 py-1 rounded-full text-xs font-black uppercase border" style="background:<?= $badge_color ?>15; color:<?= $badge_color ?>; border-color:<?= $badge_color ?>50;">
              <?= $s['type_sortie'] === 'vente_directe' ? '🛒 VENTE DIRECTE' : ($s['type_sortie'] === 'traiteur' ? '✂️ PREPARATION TRAITEUR' : '📦 AUTRE SORTIE') ?>
            </span>
            <span class="text-xs font-bold text-on-surface-variant uppercase">
              SORTIE LE <?= fmt_date_fr($s['date_sortie']) ?>
            </span>
          </div>

          <div class="text-lg font-extrabold text-on-surface mb-2">
            <?= $s['client'] ? h($s['client']) : 'Sortie de Viande' ?>
          </div>
          
          <div class="text-sm text-on-surface-variant mb-4 bg-surface-container-low p-2 rounded-lg inline-block border border-outline-variant">
            Lot Origine : <strong class="text-on-surface"><?= h($s['num_lot']) ?></strong> (<?= h($s['emoji']) ?> <?= h($s['libelle']) ?>)
          </div>

          <div class="flex justify-between items-center border-t border-outline-variant pt-4 mt-2">
            <div class="text-3xl font-extrabold text-secondary">
              <?= number_format($s['poids_kg'], 2, ',', ' ') ?> <span class="text-base font-medium text-on-surface-variant">kg</span>
            </div>

            <form method="POST" onsubmit="return confirm('Voulez-vous supprimer cette sortie ?')" class="no-print">
              <input type="hidden" name="sortie_id" value="<?= $s['id'] ?>">
              <button type="submit" name="delete_sortie" class="bg-error-container text-error rounded-xl p-2 flex items-center justify-center hover:bg-error hover:text-on-error transition-colors">
                <span class="material-symbols-outlined text-sm">delete</span>
              </button>
            </form>
          </div>

        </div>
      <?php endforeach ?>
    <?php else: ?>
      <div class="bg-surface-container-low text-primary p-4 rounded-xl border border-outline-variant">Aucune sortie enregistrée.</div>
    <?php endif ?>
  </div>
<?php endif ?>

<script>
function filtrerStocks(v) {
  v = v.toLowerCase();
  document.querySelectorAll('.stock-lot-card, .stock-sortie-card').forEach(el => {
    el.style.display = (!v || el.dataset.s.includes(v)) ? '' : 'none';
  });
}

function toggleDatePicker() {
  const dp = document.getElementById('dateFilter');
  if (dp.classList.contains('hidden')) {
    dp.classList.remove('hidden');
    dp.focus();
  } else {
    dp.classList.add('hidden');
    dp.value = '';
    filtrerDate('');
  }
}

function filtrerDate(d) {
  document.querySelectorAll('.stock-lot-card, .stock-sortie-card').forEach(el => {
    if (!d || el.dataset.date === d) {
      el.style.display = '';
    } else {
      el.style.display = 'none';
    }
  });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

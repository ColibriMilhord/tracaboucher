<?php
$page_active = 'dashboard';
$page_title  = 'Tableau de Bord';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/header.php';

$pdo    = db();
$stocks = stock_par_espece();

// Dernières entrées (5)
$derniers = $pdo->query(
    'SELECT l.*,e.code,e.libelle,e.emoji,e.couleur
     FROM lots_carcasses l JOIN especes e ON e.id=l.espece_id
     ORDER BY l.date_creation DESC LIMIT 6'
)->fetchAll();

// Stats
$nb_lots = (int)$pdo->query('SELECT COUNT(*) FROM lots_carcasses')->fetchColumn();
$nb_plats= (int)$pdo->query('SELECT COUNT(*) FROM plats_cuisines')->fetchColumn();
$dispo_total = array_sum(array_column($stocks,'dispo'));
?>

<header class="mb-6">
  <h2 class="font-display text-3xl font-extrabold text-on-surface">Vue d'ensemble</h2>
  <p class="font-body-lg text-lg text-on-surface-variant mt-2">Gestion des stocks et traçabilité en temps réel.</p>
</header>

<!-- Actions rapides -->
<section class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
  <a href="lots.php?action=ajouter" class="bg-primary text-on-primary rounded-xl p-4 flex flex-col items-center justify-center gap-2 border border-primary hover:bg-primary-container active:bg-secondary transition-colors min-h-[100px]">
    <span class="material-symbols-outlined text-[32px]">add_box</span>
    <span class="font-label-xl font-bold text-sm text-center">Nouveau Lot</span>
  </a>
  <a href="sorties.php?action=ajouter" class="bg-surface-container-lowest text-on-surface border border-outline-variant rounded-xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-surface-container-low active:bg-surface-variant transition-colors min-h-[100px]">
    <span class="material-symbols-outlined text-[32px] text-secondary">output</span>
    <span class="font-label-xl font-bold text-sm text-center">Sortie Traiteur</span>
  </a>
  <a href="lots.php" class="bg-surface-container-lowest text-on-surface border border-outline-variant rounded-xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-surface-container-low active:bg-surface-variant transition-colors min-h-[100px]">
    <span class="material-symbols-outlined text-[32px] text-secondary">document_scanner</span>
    <span class="font-label-xl font-bold text-sm text-center">Scan Doc</span>
  </a>
  <a href="lots.php?action=modifier" class="bg-surface-container-lowest text-on-surface border border-outline-variant rounded-xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-surface-container-low active:bg-surface-variant transition-colors min-h-[100px]">
    <span class="material-symbols-outlined text-[32px] text-secondary">edit</span>
    <span class="font-label-xl font-bold text-sm text-center">Gérer</span>
  </a>
</section>

<!-- Stock par espèce -->
<section class="mb-8">
  <h3 class="font-headline-md text-xl font-bold text-on-surface mb-4">Lots en Stock (Poids Restant)</h3>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <?php foreach ($stocks as $s):
      if ($s['entree'] <= 0) continue;
      $pct = $s['pct_utilise'];
      $progress_color = $pct >= 80 ? 'bg-secondary' : ($pct >= 95 ? 'bg-error' : 'bg-primary');
    ?>
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-6 flex flex-col">
      <div class="flex items-center justify-between mb-4">
        <span class="font-label-xl text-lg font-bold text-on-surface flex items-center gap-2">
          <span><?= $s['emoji'] ?></span> <?= h($s['libelle']) ?>
        </span>
      </div>
      <div class="font-display text-4xl font-extrabold text-on-surface mb-4">
        <?= number_format($s['dispo'],1,',',' ') ?> <span class="font-body-md text-base font-normal text-on-surface-variant">kg</span>
      </div>
      <div class="w-full bg-surface-variant rounded-full h-2 mt-auto">
        <div class="<?= $progress_color ?> h-2 rounded-full" style="width: <?= min(100,$pct) ?>%"></div>
      </div>
      <div class="flex justify-between text-xs text-on-surface-variant mt-2">
        <span>Entré : <?= number_format($s['entree'],1) ?> kg</span>
        <span><?= $pct ?>% utilisé</span>
      </div>
    </div>
    <?php endforeach ?>
  </div>
  <?php if ($dispo_total <= 0): ?>
    <div class="bg-surface-container-low text-primary p-4 rounded-xl border border-outline-variant mt-4">
      Aucun stock enregistré. <a href="lots.php?action=ajouter" class="font-bold underline">Créer un lot →</a>
    </div>
  <?php endif ?>
</section>

<!-- Dernières entrées -->
<section class="mb-8">
  <div class="flex items-center justify-between mb-4">
    <h3 class="font-headline-md text-xl font-bold text-on-surface">Dernières entrées</h3>
    <a href="lots.php" class="text-sm font-bold text-primary hover:underline">Voir tout →</a>
  </div>
  
  <div class="bg-surface-container-lowest border border-outline-variant rounded-xl flex flex-col overflow-hidden">
    <?php foreach ($derniers as $l): ?>
    <a href="lots.php?id=<?= $l['id'] ?>" class="border-b border-outline-variant last:border-0 p-4 px-6 flex items-center justify-between min-h-[64px] hover:bg-surface-container-low transition-colors cursor-pointer">
      <div class="flex items-center gap-4">
        <div class="bg-surface-container rounded-lg p-2 flex items-center justify-center text-2xl">
          <?= $l['emoji'] ?>
        </div>
        <div>
          <p class="font-label-xl font-bold text-on-surface"><?= h($l['num_lot']) ?></p>
          <p class="font-body-md text-sm text-on-surface-variant">
            <?= $l['fournisseur'] ? h($l['fournisseur']) : 'Sans fournisseur' ?> • <?= fmt_date($l['date_entree']) ?>
          </p>
        </div>
      </div>
      <div class="text-right flex flex-col items-end">
        <span class="font-headline-md text-xl font-extrabold text-primary"><?= number_format($l['poids_carcasse_kg'],0,',',' ') ?> kg</span>
        <span class="bg-primary-container text-on-primary-container px-2 py-1 rounded-full text-xs font-bold mt-1">Conforme</span>
      </div>
    </a>
    <?php endforeach ?>
  </div>
</section>

<!-- Mini stats -->
<section class="grid grid-cols-2 gap-4">
  <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 flex flex-col items-center justify-center">
    <div class="font-display text-3xl font-extrabold text-secondary"><?= $nb_lots ?></div>
    <div class="text-xs text-on-surface-variant mt-1 font-bold uppercase">Lots totaux</div>
  </div>
  <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 flex flex-col items-center justify-center">
    <div class="font-display text-3xl font-extrabold text-secondary"><?= $nb_plats ?></div>
    <div class="text-xs text-on-surface-variant mt-1 font-bold uppercase">Plats cuisinés</div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

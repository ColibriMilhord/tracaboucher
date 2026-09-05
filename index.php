<?php
$page_active = 'dashboard';
$page_title  = 'Tableau de bord';
require_once __DIR__ . '/includes/auth.php';
$moi = exiger_connexion();

$pdo = db();

$debut_mois = date('Y-m-01');
$stat = function (string $sql, array $p = []) use ($pdo) {
    $q = $pdo->prepare($sql); $q->execute($p); return $q->fetchColumn();
};

$nb_entrees_mois = (int)$stat('SELECT COUNT(*) FROM lots_entree WHERE date_entree >= ?', [$debut_mois]);
$nb_fabs_mois    = (int)$stat('SELECT COUNT(*) FROM lots_sortie WHERE date_fabrication >= ?', [$debut_mois]);
$kg_fabs_mois    = (float)$stat("SELECT COALESCE(SUM(quantite),0) FROM lots_sortie WHERE date_fabrication >= ? AND unite='kg'", [$debut_mois]);

// Températures hors tolérance 4 °C ± 2 °C sur les matières réfrigérées
$hors_temp = $pdo->prepare(
    'SELECT e.id, e.num_lot, e.temperature, e.date_entree, e.fournisseur
     FROM lots_entree e JOIN types_matiere t ON t.id=e.type_id
     WHERE t.categorie = ? AND (e.temperature < 2 OR e.temperature > 6)
       AND e.date_entree >= ? ORDER BY e.date_entree DESC LIMIT 10');
$hors_temp->execute(['viande', date('Y-m-d', strtotime('-90 days'))]);
$hors_temp = $hors_temp->fetchAll();

$dernieres_entrees = $pdo->query(
    'SELECT e.*, t.code, t.couleur FROM lots_entree e JOIN types_matiere t ON t.id=e.type_id
     ORDER BY e.id DESC LIMIT 5')->fetchAll();

$dernieres_fabs = $pdo->query('SELECT * FROM lots_sortie ORDER BY id DESC LIMIT 5')->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<h2 class="font-headline-lg text-2xl font-bold text-primary mb-1"><?= h(reglage('nom_atelier', 'Atelier de transformation')) ?></h2>
<p class="text-sm text-on-surface-variant mb-6">Traçabilité entrées / fabrications</p>

<!-- Actions principales -->
<div class="grid grid-cols-2 gap-3 mb-8">
  <a href="entrees.php?action=nouveau"
     class="bg-primary text-on-primary rounded-xl p-5 flex flex-col items-center gap-2 active:scale-[.98] transition">
    <span class="material-symbols-outlined text-4xl">move_to_inbox</span>
    <span class="font-bold text-center leading-tight">Nouvelle<br>entrée</span>
  </a>
  <a href="fabrications.php?action=nouveau"
     class="bg-secondary text-on-secondary rounded-xl p-5 flex flex-col items-center gap-2 active:scale-[.98] transition">
    <span class="material-symbols-outlined text-4xl">outbox</span>
    <span class="font-bold text-center leading-tight">Nouvelle<br>fabrication</span>
  </a>
</div>

<!-- Chiffres du mois -->
<div class="grid grid-cols-3 gap-3 mb-8">
  <?php foreach ([
      ['Entrées', $nb_entrees_mois, 'ce mois'],
      ['Fabrications', $nb_fabs_mois, 'ce mois'],
      ['Produit', rtrim(rtrim(number_format($kg_fabs_mois, 1, ',', ' '), '0'), ',') . ' kg', 'ce mois'],
  ] as [$lib, $val, $sub]): ?>
  <div class="bg-surface rounded-xl border border-outline-variant p-4 text-center">
    <div class="font-headline-lg text-2xl font-bold text-primary"><?= $val ?></div>
    <div class="text-xs font-semibold"><?= $lib ?></div>
    <div class="text-xs text-on-surface-variant"><?= $sub ?></div>
  </div>
  <?php endforeach ?>
</div>

<?php if ($hors_temp): ?>
<div class="bg-error-container text-on-error-container rounded-xl p-4 mb-8">
  <div class="flex items-center gap-2 font-bold mb-2">
    <span class="material-symbols-outlined">thermostat</span>
    Températures hors tolérance (4 °C ± 2 °C)
  </div>
  <ul class="text-sm flex flex-col gap-1">
    <?php foreach ($hors_temp as $t): ?>
    <li><a class="underline" href="entrees.php?action=voir&id=<?= (int)$t['id'] ?>"><?= h($t['num_lot']) ?></a>
        — <?= fmt_temp((float)$t['temperature']) ?> · <?= h($t['fournisseur']) ?> · <?= fmt_date($t['date_entree']) ?></li>
    <?php endforeach ?>
  </ul>
</div>
<?php endif ?>

<div class="grid md:grid-cols-2 gap-6">
  <div>
    <div class="flex items-center justify-between mb-3">
      <h3 class="font-headline-md font-bold">Dernières entrées</h3>
      <a href="entrees.php" class="text-sm text-primary font-semibold">Tout voir</a>
    </div>
    <?php if (!$dernieres_entrees): ?>
    <p class="text-sm text-on-surface-variant bg-surface rounded-xl border border-outline-variant p-5">Aucune entrée.</p>
    <?php else: ?>
    <div class="flex flex-col gap-2">
      <?php foreach ($dernieres_entrees as $e): ?>
      <a href="entrees.php?action=voir&id=<?= (int)$e['id'] ?>" class="bg-surface rounded-xl border border-outline-variant p-3 flex items-center gap-3 hover:bg-surface-container-low">
        <span class="lot-badge text-xs font-bold text-white px-2 py-1 rounded" style="background:<?= h($e['couleur']) ?>"><?= h($e['code']) ?></span>
        <div class="flex-1 min-w-0">
          <div class="lot-badge font-bold text-sm"><?= h($e['num_lot']) ?></div>
          <div class="text-xs text-on-surface-variant truncate"><?= h($e['fournisseur']) ?></div>
        </div>
        <div class="text-xs text-on-surface-variant"><?= fmt_date($e['date_entree']) ?></div>
      </a>
      <?php endforeach ?>
    </div>
    <?php endif ?>
  </div>

  <div>
    <div class="flex items-center justify-between mb-3">
      <h3 class="font-headline-md font-bold">Dernières fabrications</h3>
      <a href="fabrications.php" class="text-sm text-primary font-semibold">Tout voir</a>
    </div>
    <?php if (!$dernieres_fabs): ?>
    <p class="text-sm text-on-surface-variant bg-surface rounded-xl border border-outline-variant p-5">Aucune fabrication.</p>
    <?php else: ?>
    <div class="flex flex-col gap-2">
      <?php foreach ($dernieres_fabs as $f): ?>
      <a href="fabrications.php?action=voir&id=<?= (int)$f['id'] ?>" class="bg-surface rounded-xl border border-outline-variant p-3 flex items-center gap-3 hover:bg-surface-container-low">
        <div class="flex-1 min-w-0">
          <div class="lot-badge font-bold text-sm text-primary"><?= h($f['num_lot']) ?></div>
          <div class="text-xs text-on-surface-variant truncate"><?= h($f['produit']) ?></div>
        </div>
        <div class="text-xs text-on-surface-variant"><?= fmt_date($f['date_fabrication']) ?></div>
      </a>
      <?php endforeach ?>
    </div>
    <?php endif ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

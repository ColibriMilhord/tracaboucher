<?php
$page_active = 'tracabilite';
$page_title  = 'Traçabilité';
require_once __DIR__ . '/includes/auth.php';
$moi = exiger_connexion();

$pdo = db();
$q   = trim($_GET['q'] ?? '');

$res_entrees = $res_sorties = [];
if ($q !== '') {
    $s = $pdo->prepare(
        'SELECT e.*, t.code, t.libelle AS type_libelle, t.couleur
         FROM lots_entree e JOIN types_matiere t ON t.id=e.type_id
         WHERE e.num_lot LIKE ? OR e.fournisseur LIKE ? OR e.num_lot_fournisseur LIKE ?
         ORDER BY e.date_entree DESC LIMIT 50');
    $s->execute(["%$q%", "%$q%", "%$q%"]);
    $res_entrees = $s->fetchAll();

    $s2 = $pdo->prepare(
        'SELECT * FROM lots_sortie WHERE num_lot LIKE ? OR produit LIKE ?
         ORDER BY date_fabrication DESC LIMIT 50');
    $s2->execute(["%$q%", "%$q%"]);
    $res_sorties = $s2->fetchAll();
}

require __DIR__ . '/includes/header.php';
?>

<h2 class="font-headline-lg text-2xl font-bold text-primary mb-1">Traçabilité</h2>
<p class="text-sm text-on-surface-variant mb-5">
  Saisissez un n° de lot (entrée ou fabrication), un fournisseur ou un produit pour remonter toute la chaîne.
</p>

<form method="get" class="flex gap-2 mb-6">
  <input type="text" name="q" value="<?= h($q) ?>" autofocus placeholder="ex : JB-100726, <?= h(date('dmy')) ?>-1, Merguez…"
         class="flex-1 rounded-full border-outline-variant">
  <button class="bg-primary text-on-primary rounded-full px-6 font-bold">Chercher</button>
</form>

<?php if ($q === ''): ?>
<div class="bg-surface rounded-xl border border-outline-variant p-8 text-center text-on-surface-variant">
  <span class="material-symbols-outlined text-4xl">account_tree</span>
  <p class="mt-2 text-sm">Deux sens de recherche :<br>
    un lot d'entrée → toutes les fabrications qui en sont issues<br>
    un lot de fabrication → toutes les matières premières utilisées</p>
</div>

<?php elseif (!$res_entrees && !$res_sorties): ?>
<div class="bg-surface rounded-xl border border-outline-variant p-8 text-center text-on-surface-variant">
  <p class="text-sm">Aucun résultat pour « <?= h($q) ?> ».</p>
</div>

<?php else: ?>

<?php if ($res_sorties): ?>
<h3 class="font-headline-md font-bold mb-3">Fabrications (<?= count($res_sorties) ?>) — traçabilité ascendante</h3>
<div class="flex flex-col gap-4 mb-8">
  <?php foreach ($res_sorties as $s): $sources = entrees_de_sortie((int)$s['id']); ?>
  <div class="bg-surface rounded-xl border border-outline-variant p-5">
    <div class="flex items-start gap-3 mb-3">
      <div class="flex-1">
        <a href="fabrications.php?action=voir&id=<?= (int)$s['id'] ?>" class="lot-badge text-lg font-bold text-primary"><?= h($s['num_lot']) ?></a>
        <div class="text-sm"><?= h($s['produit']) ?> · <?= fmt_date($s['date_fabrication']) ?> · <?= fmt_qte((float)$s['quantite'], $s['unite']) ?></div>
        <div class="text-xs text-on-surface-variant">
          <?= LIB_CONDITIONNEMENT[$s['conditionnement']] ?? '' ?> · <?= LIB_CONSERVATION[$s['conservation']] ?? '' ?>
          <?= $s['dlc'] ? ' · DLC ' . fmt_date($s['dlc']) : '' ?>
        </div>
      </div>
      <a href="export.php?type=sortie&id=<?= (int)$s['id'] ?>" class="text-xs text-primary font-semibold flex items-center gap-1">
        <span class="material-symbols-outlined text-base">download</span>CSV
      </a>
    </div>
    <div class="border-l-2 border-outline-variant pl-4 flex flex-col gap-2">
      <?php foreach ($sources as $e): ?>
      <a href="entrees.php?action=voir&id=<?= (int)$e['id'] ?>" class="flex items-center gap-2 text-sm hover:underline">
        <span class="lot-badge text-xs font-bold text-white px-2 py-0.5 rounded" style="background:<?= h($e['couleur']) ?>"><?= h($e['code']) ?></span>
        <span class="lot-badge font-semibold"><?= h($e['num_lot']) ?></span>
        <span class="text-on-surface-variant truncate"><?= h($e['fournisseur']) ?> · <?= fmt_date($e['date_entree']) ?></span>
        <?php if ($e['qte_utilisee'] !== null): ?><span class="ml-auto text-xs font-semibold"><?= fmt_qte((float)$e['qte_utilisee']) ?></span><?php endif ?>
      </a>
      <?php endforeach ?>
      <?php if (!$sources): ?><p class="text-sm text-error">Aucun lot d'entrée rattaché.</p><?php endif ?>
    </div>
  </div>
  <?php endforeach ?>
</div>
<?php endif ?>

<?php if ($res_entrees): ?>
<h3 class="font-headline-md font-bold mb-3">Entrées (<?= count($res_entrees) ?>) — traçabilité descendante</h3>
<div class="flex flex-col gap-4">
  <?php foreach ($res_entrees as $e): $usages = sorties_de_entree((int)$e['id']); ?>
  <div class="bg-surface rounded-xl border border-outline-variant p-5">
    <div class="flex items-start gap-3 mb-3">
      <span class="lot-badge text-xs font-bold text-white px-2 py-1 rounded" style="background:<?= h($e['couleur']) ?>"><?= h($e['code']) ?></span>
      <div class="flex-1">
        <a href="entrees.php?action=voir&id=<?= (int)$e['id'] ?>" class="lot-badge text-lg font-bold text-primary"><?= h($e['num_lot']) ?></a>
        <div class="text-sm"><?= h($e['type_libelle']) ?> · <?= h($e['fournisseur']) ?> · <?= fmt_date($e['date_entree']) ?></div>
        <div class="text-xs text-on-surface-variant">
          <?= fmt_temp((float)$e['temperature']) ?><?= $e['poids_kg'] !== null ? ' · ' . fmt_qte((float)$e['poids_kg']) : '' ?>
        </div>
      </div>
      <a href="export.php?type=entree&id=<?= (int)$e['id'] ?>" class="text-xs text-primary font-semibold flex items-center gap-1">
        <span class="material-symbols-outlined text-base">download</span>CSV
      </a>
    </div>
    <div class="border-l-2 border-outline-variant pl-4 flex flex-col gap-2">
      <?php foreach ($usages as $s): ?>
      <a href="fabrications.php?action=voir&id=<?= (int)$s['id'] ?>" class="flex items-center gap-2 text-sm hover:underline">
        <span class="lot-badge font-semibold text-primary"><?= h($s['num_lot']) ?></span>
        <span class="text-on-surface-variant truncate"><?= h($s['produit']) ?> · <?= fmt_date($s['date_fabrication']) ?></span>
        <?php if ($s['qte_utilisee'] !== null): ?><span class="ml-auto text-xs font-semibold"><?= fmt_qte((float)$s['qte_utilisee']) ?></span><?php endif ?>
      </a>
      <?php endforeach ?>
      <?php if (!$usages): ?><p class="text-sm text-on-surface-variant">Ce lot n'a encore servi à aucune fabrication.</p><?php endif ?>
    </div>
  </div>
  <?php endforeach ?>
</div>
<?php endif ?>

<?php endif ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

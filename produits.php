<?php
$page_active = 'produits';
$page_title  = 'Produits';
require_once __DIR__ . '/includes/auth.php';
$moi = exiger_admin();

$pdo = db();

if (!referentiel_produits_pret()) {
    require __DIR__ . '/includes/header.php';
    echo '<div class="bg-error-container text-on-error-container rounded-xl px-4 py-4 text-sm">'
       . 'Le référentiel produits n\'est pas encore installé. '
       . '<a class="underline font-semibold" href="maj.php">Lancer la mise à jour de la base</a>.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$msg = '';
$LIB_CLASSE = [
    ''             => '— aucune —',
    'viande_bovine'=> 'Viande bovine (mentions réglementaires)',
    'viande'       => 'Viande (autres espèces)',
    'traiteur'     => 'Plat cuisiné / traiteur',
    'legume'       => 'Fruits et légumes',
];

// ── Enregistrement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $pid     = (int)($_POST['id'] ?? 0);
    $libelle = trim($_POST['libelle'] ?? '');
    $court   = trim($_POST['libelle_court'] ?? '');
    $famille = trim($_POST['famille'] ?? '');
    $classe  = $_POST['classe_traca'] ?? '';
    $prix    = nombre($_POST['prix_kg'] ?? '');
    $dlc     = trim($_POST['dlc_jours'] ?? '');
    $cond    = ($_POST['conditionnement'] ?? '') ?: null;
    $consv   = ($_POST['conservation'] ?? '') ?: null;
    $actif   = isset($_POST['actif']) ? 1 : 0;
    $plu     = preg_replace('/\D/', '', $_POST['plu'] ?? '');
    $ean     = preg_replace('/\D/', '', $_POST['ean13'] ?? '');

    if ($plu !== '') $plu = str_pad(substr($plu, 0, 4), 4, '0', STR_PAD_LEFT);
    if ($plu === '') $plu = prochain_plu();
    if ($ean === '') $ean = ean13_interne($plu);

    if (!isset($LIB_CLASSE[$classe])) $classe = '';
    if ($cond  !== null && !isset(LIB_CONDITIONNEMENT[$cond]))  $cond  = null;
    if ($consv !== null && !isset(LIB_CONSERVATION[$consv]))    $consv = null;

    if ($libelle === '') {
        $msg = 'Le libellé est obligatoire.';
    } elseif ($plu === '') {
        $msg = 'Plus aucun PLU disponible (9999 produits atteints).';
    } elseif (!ean13_valide($ean)) {
        $msg = 'Le code EAN saisi est invalide : 13 chiffres avec une clé de contrôle correcte. '
             . 'Laissez le champ vide pour qu\'il soit calculé automatiquement.';
    } else {
        try {
            if ($pid) {
                $pdo->prepare(
                    'UPDATE produits SET plu=?, ean13=?, libelle=?, libelle_court=?, famille=?,
                     classe_traca=?, prix_kg=?, dlc_jours=?, conditionnement=?, conservation=?, actif=?
                     WHERE id=?'
                )->execute([$plu, $ean, $libelle, $court ?: null, $famille ?: null, $classe ?: null,
                            $prix, $dlc === '' ? null : (int)$dlc, $cond, $consv, $actif, $pid]);
            } else {
                $ordre = (int)$pdo->query('SELECT COALESCE(MAX(ordre),0)+10 FROM produits')->fetchColumn();
                $pdo->prepare(
                    'INSERT INTO produits (plu, ean13, libelle, libelle_court, famille, classe_traca,
                     prix_kg, dlc_jours, conditionnement, conservation, actif, ordre)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([$plu, $ean, $libelle, $court ?: null, $famille ?: null, $classe ?: null,
                            $prix, $dlc === '' ? null : (int)$dlc, $cond, $consv, $actif, $ordre]);
            }
            header('Location: produits.php?msg=ok');
            exit;
        } catch (PDOException $e) {
            $msg = strpos($e->getMessage(), 'uk_plu') !== false
                 ? 'Ce PLU est déjà attribué à un autre produit.'
                 : 'Erreur : ' . $e->getMessage();
        }
    }
}

$ok = ($_GET['msg'] ?? '') === 'ok' ? 'Produit enregistré.' : '';
$produits = $pdo->query('SELECT * FROM produits ORDER BY actif DESC, ordre, libelle')->fetchAll();

$edit = null;
if (($_GET['action'] ?? '') === 'modifier' && ($eid = (int)($_GET['id'] ?? 0))) {
    $edit = produit_par_id($eid);
}
$plu_propose = $edit['plu'] ?? prochain_plu();

require __DIR__ . '/includes/header.php';
?>

<div class="flex items-start justify-between gap-3 mb-1">
  <h2 class="font-headline-lg text-2xl font-bold text-primary">Produits</h2>
  <?php if ($produits): ?>
  <div class="flex flex-col items-end gap-1 shrink-0 mt-1">
    <a href="export.php?type=dfs_articulo" class="text-sm text-primary font-semibold flex items-center gap-1">
      <span class="material-symbols-outlined text-base">download</span>Fichier balance (format DFS)
    </a>
    <a href="export.php?type=dfs_articles" class="text-xs text-on-surface-variant flex items-center gap-1">
      <span class="material-symbols-outlined text-sm">download</span>Version lisible
    </a>
  </div>
  <?php endif ?>
</div>
<p class="text-sm text-on-surface-variant mb-6">
  Ce que vous fabriquez. Chaque produit reçoit un PLU à 4 chiffres et un code EAN-13 interne,
  les deux formats exigés par l'étiqueteuse Dibal.
</p>

<?php if ($msg): ?><div class="bg-error-container text-on-error-container rounded-xl px-4 py-3 mb-4 text-sm"><?= h($msg) ?></div><?php endif ?>
<?php if ($ok):  ?><div class="bg-primary-container text-on-primary-container rounded-xl px-4 py-3 mb-4 text-sm"><?= h($ok) ?></div><?php endif ?>

<?php if ($produits && !$edit): ?>
<div class="bg-surface rounded-xl border border-outline-variant p-5 mb-6">
  <div class="flex items-center gap-2 mb-3">
    <span class="material-symbols-outlined text-on-surface-variant">search</span>
    <input type="text" id="rech-produit" placeholder="Filtrer (nom, PLU, famille…)"
           class="flex-1 rounded-full border-outline-variant text-sm">
    <span class="text-xs text-on-surface-variant shrink-0"><?= count($produits) ?> produits</span>
  </div>
  <div class="flex flex-col gap-2" id="liste-produits">
    <?php foreach ($produits as $p): $tb = taux_bio((int)$p['id']); ?>
    <div class="prod-ligne flex items-center gap-3 py-2 border-b border-outline-variant last:border-0 <?= $p['actif'] ? '' : 'opacity-40' ?>"
         data-cherche="<?= h(mb_strtolower($p['plu'].' '.$p['libelle'].' '.$p['famille'].' '.$p['ean13'])) ?>">
      <span class="lot-badge text-xs font-bold bg-surface-container-high px-2 py-1 rounded shrink-0"><?= h($p['plu']) ?></span>
      <div class="flex-1 min-w-0">
        <div class="text-sm font-semibold truncate"><?= h($p['libelle']) ?></div>
        <div class="text-xs text-on-surface-variant lot-badge">
          <?= h($p['ean13']) ?><?= $p['famille'] ? ' · ' . h($p['famille']) : '' ?><?= $p['actif'] ? '' : ' · inactif' ?>
        </div>
      </div>
      <span class="text-xs shrink-0 px-2 py-1 rounded <?= $tb['mention'] === 'bio' ? 'bg-primary-container text-on-primary-container font-semibold' : 'text-on-surface-variant' ?>">
        <?= $tb['taux'] === null ? 'composition à faire' : number_format($tb['taux'], 1, ',', ' ') . ' % bio' ?>
      </span>
      <a href="recette.php?produit_id=<?= (int)$p['id'] ?>" title="Composition"
         class="material-symbols-outlined text-primary min-w-[48px] min-h-[48px] rounded-full flex items-center justify-center active:bg-surface-container">receipt_long</a>
      <a href="produits.php?action=modifier&id=<?= (int)$p['id'] ?>" title="Modifier"
         class="material-symbols-outlined text-primary min-w-[48px] min-h-[48px] rounded-full flex items-center justify-center active:bg-surface-container">edit</a>
    </div>
    <?php endforeach ?>
  </div>
  <p id="rech-vide" class="hidden text-sm text-on-surface-variant text-center py-4">Aucun produit ne correspond.</p>
</div>

<script>
(function () {
  var r = document.getElementById('rech-produit');
  if (!r) return;
  r.addEventListener('input', function () {
    var q = r.value.trim().toLowerCase(), vus = 0;
    document.querySelectorAll('#liste-produits .prod-ligne').forEach(function (l) {
      var ok = !q || l.dataset.cherche.indexOf(q) !== -1;
      l.hidden = !ok; if (ok) vus++;
    });
    document.getElementById('rech-vide').classList.toggle('hidden', vus > 0);
  });
})();
</script>
<?php endif ?>

<div class="bg-surface rounded-xl border border-outline-variant p-5">
  <?php if ($edit): ?>
  <a href="produits.php" class="inline-flex items-center gap-1 text-sm text-primary font-semibold mb-3">
    <span class="material-symbols-outlined">arrow_back</span>Retour à la liste
  </a>
  <?php endif ?>
  <h3 class="font-headline-md font-bold mb-4"><?= $edit ? 'Modifier « ' . h($edit['libelle']) . ' »' : 'Ajouter un produit' ?></h3>
  <form method="post" class="grid sm:grid-cols-2 gap-4">
    <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">

    <div class="sm:col-span-2">
      <label class="block text-sm font-semibold mb-1">Libellé <span class="text-error">*</span></label>
      <input type="text" name="libelle" required value="<?= h($edit['libelle'] ?? '') ?>" <?= $edit ? 'autofocus' : '' ?>
             placeholder="ex : Merguez de bœuf" class="w-full rounded-xl border-outline-variant">
    </div>

    <div>
      <label class="block text-sm font-semibold mb-1">Libellé court</label>
      <input type="text" name="libelle_court" maxlength="40" value="<?= h($edit['libelle_court'] ?? '') ?>"
             placeholder="tel qu'imprimé sur l'étiquette" class="w-full rounded-xl border-outline-variant">
      <p class="text-xs text-on-surface-variant mt-1">40 caractères maximum.</p>
    </div>
    <div>
      <label class="block text-sm font-semibold mb-1">Famille</label>
      <input type="text" name="famille" value="<?= h($edit['famille'] ?? '') ?>"
             placeholder="ex : Traiteur" class="w-full rounded-xl border-outline-variant">
    </div>

    <div>
      <label class="block text-sm font-semibold mb-1">PLU</label>
      <input type="text" name="plu" inputmode="numeric" maxlength="4" value="<?= h($plu_propose) ?>"
             class="w-full rounded-xl border-outline-variant lot-badge">
      <p class="text-xs text-on-surface-variant mt-1">Proposé automatiquement, modifiable.</p>
    </div>
    <div>
      <label class="block text-sm font-semibold mb-1">Code EAN-13</label>
      <input type="text" name="ean13" inputmode="numeric" maxlength="13" value="<?= h($edit['ean13'] ?? '') ?>"
             placeholder="calculé si vide" class="w-full rounded-xl border-outline-variant lot-badge">
      <p class="text-xs text-on-surface-variant mt-1">
        Laissez vide : un code interne sera calculé (<span class="lot-badge"><?= h(ean13_interne($plu_propose ?: '0001')) ?></span>).
      </p>
    </div>

    <div>
      <label class="block text-sm font-semibold mb-1">Prix au kg (€)</label>
      <input type="text" inputmode="decimal" name="prix_kg" value="<?= h($edit['prix_kg'] ?? '') ?>"
             class="w-full rounded-xl border-outline-variant">
    </div>
    <div>
      <label class="block text-sm font-semibold mb-1">Durée de vie (jours)</label>
      <input type="text" inputmode="numeric" name="dlc_jours" value="<?= h($edit['dlc_jours'] ?? '') ?>"
             placeholder="ex : 8" class="w-full rounded-xl border-outline-variant">
      <p class="text-xs text-on-surface-variant mt-1">Sert à proposer la DLC à la fabrication.</p>
    </div>

    <div>
      <label class="block text-sm font-semibold mb-1">Conditionnement habituel</label>
      <select name="conditionnement" class="w-full rounded-xl border-outline-variant">
        <option value="">—</option>
        <?php foreach (LIB_CONDITIONNEMENT as $k => $l): ?>
        <option value="<?= $k ?>" <?= ($edit['conditionnement'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach ?>
      </select>
    </div>
    <div>
      <label class="block text-sm font-semibold mb-1">Conservation habituelle</label>
      <select name="conservation" class="w-full rounded-xl border-outline-variant">
        <option value="">—</option>
        <?php foreach (LIB_CONSERVATION as $k => $l): ?>
        <option value="<?= $k ?>" <?= ($edit['conservation'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach ?>
      </select>
    </div>

    <div class="sm:col-span-2">
      <label class="block text-sm font-semibold mb-1">Classe de traçabilité (étiquette)</label>
      <select name="classe_traca" class="w-full rounded-xl border-outline-variant">
        <?php foreach ($LIB_CLASSE as $k => $l): ?>
        <option value="<?= $k ?>" <?= ($edit['classe_traca'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach ?>
      </select>
      <p class="text-xs text-on-surface-variant mt-1">
        « Viande bovine » impose les mentions du règlement 1760/2000 : pays de naissance, d'élevage,
        d'abattage et de découpe avec les n° d'agrément.
      </p>
    </div>

    <label class="sm:col-span-2 flex items-center gap-2 text-sm">
      <input type="checkbox" name="actif" value="1" <?= (!$edit || $edit['actif']) ? 'checked' : '' ?> class="rounded">
      Actif (proposé à la saisie d'une fabrication)
    </label>

    <div class="sm:col-span-2 flex gap-2">
      <button name="save" value="1" class="bg-primary text-on-primary rounded-full px-6 py-3 font-bold text-sm"><?= $edit ? 'Enregistrer' : 'Ajouter' ?></button>
      <?php if ($edit): ?><a href="produits.php" class="bg-surface-container-high rounded-full px-6 py-3 font-bold text-sm">Annuler</a><?php endif ?>
    </div>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

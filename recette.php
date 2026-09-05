<?php
$page_active = 'produits';
$page_title  = 'Composition';
require_once __DIR__ . '/includes/auth.php';
$moi = exiger_admin();

$pdo = db();
$produit_id = (int)($_GET['produit_id'] ?? $_POST['produit_id'] ?? 0);

if (!recettes_pretes()) {
    require __DIR__ . '/includes/header.php';
    echo '<div class="bg-error-container text-on-error-container rounded-xl px-4 py-4 text-sm">'
       . 'Le module de composition n\'est pas encore installé. '
       . '<a class="underline font-semibold" href="maj.php">Lancer la mise à jour de la base</a>.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$produit = $produit_id ? produit_par_id($produit_id) : null;
if (!$produit) {
    require __DIR__ . '/includes/header.php';
    echo '<p class="text-sm">Produit introuvable. <a class="text-primary underline" href="produits.php">Retour</a></p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$msg = '';

// ── Enregistrement de la composition entière
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $libelles = $_POST['libelle']  ?? [];
    $qtes     = $_POST['quantite'] ?? [];
    $natures  = $_POST['nature']   ?? [];
    $bios     = $_POST['bio']      ?? [];

    $lignes = [];
    foreach ($libelles as $i => $lib) {
        $lib = trim($lib);
        $q   = nombre($qtes[$i] ?? '');
        if ($lib === '' || $q === null || $q <= 0) continue;
        $nat = $natures[$i] ?? 'agricole';
        if (!isset(LIB_NATURE[$nat])) $nat = 'agricole';
        $lignes[] = [$lib, $q, $nat, ($bios[$i] ?? '0') === '1' ? 1 : 0];
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM recette_lignes WHERE produit_id=?')->execute([$produit_id]);
        $ins = $pdo->prepare('INSERT INTO recette_lignes (produit_id, libelle, quantite, nature, bio, ordre) VALUES (?,?,?,?,?,?)');
        foreach ($lignes as $n => [$lib, $q, $nat, $bio]) {
            $ins->execute([$produit_id, $lib, $q, $nat, $bio, ($n + 1) * 10]);
        }
        $pdo->commit();
        header('Location: recette.php?produit_id=' . $produit_id . '&msg=ok');
        exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = 'Erreur : ' . $e->getMessage();
    }
}

$ok = ($_GET['msg'] ?? '') === 'ok' ? 'Composition enregistrée.' : '';
$t  = taux_bio($produit_id);
$lignes = $t['lignes'];
if (!$lignes) $lignes = [['libelle' => '', 'quantite' => '', 'nature' => 'agricole', 'bio' => 1]];

require __DIR__ . '/includes/header.php';
?>

<div class="flex items-center gap-3 mb-1">
  <a href="produits.php" class="material-symbols-outlined text-on-surface-variant hover:bg-surface-container p-2 rounded-full">arrow_back</a>
  <div class="flex-1 min-w-0">
    <h2 class="font-headline-lg text-2xl font-bold text-primary truncate">Composition</h2>
    <div class="text-sm text-on-surface-variant"><?= h($produit['libelle']) ?> · PLU <?= h($produit['plu']) ?></div>
  </div>
</div>
<p class="text-sm text-on-surface-variant mb-5">
  Les quantités d'une fabrication type. Le règlement bio calcule le taux sur les seuls
  ingrédients agricoles : l'eau et le sel en sont exclus, quel que soit leur poids.
</p>

<?php if ($msg): ?><div class="bg-error-container text-on-error-container rounded-xl px-4 py-3 mb-4 text-sm"><?= h($msg) ?></div><?php endif ?>
<?php if ($ok):  ?><div class="bg-primary-container text-on-primary-container rounded-xl px-4 py-3 mb-4 text-sm"><?= h($ok) ?></div><?php endif ?>

<!-- Résultat du calcul -->
<?php
$couleur = ['bio' => 'bg-primary-container text-on-primary-container',
            'ingredients_bio' => 'bg-surface-container-high',
            'non_bio' => 'bg-surface-container-high',
            'inconnu' => 'bg-surface-container-high'][$t['mention']];
?>
<div class="<?= $couleur ?> rounded-xl p-5 mb-6">
  <div class="flex items-baseline gap-3 mb-2">
    <span class="font-headline-lg text-3xl font-bold"><?= $t['taux'] === null ? '—' : number_format($t['taux'], 2, ',', ' ') . ' %' ?></span>
    <span class="text-sm">d'ingrédients agricoles biologiques</span>
  </div>
  <div class="text-sm font-semibold"><?= h(libelle_mention_bio($t['mention'])) ?></div>
  <?php if ($t['agricole'] > 0): ?>
  <div class="text-xs mt-2 opacity-90">
    <?= fmt_qte($t['bio']) ?> bio sur <?= fmt_qte($t['agricole']) ?> d'ingrédients agricoles ·
    seuil réglementaire <?= number_format(SEUIL_BIO, 0) ?> %
  </div>
  <?php endif ?>
  <?php if ($t['mention'] === 'ingredients_bio'): ?>
  <div class="text-xs mt-2 bg-surface rounded-lg px-3 py-2">
    Sous le seuil : ni Eurofeuille, ni « bio » dans la dénomination. Les ingrédients
    biologiques ne peuvent être signalés que dans la liste d'ingrédients.
  </div>
  <?php endif ?>
</div>

<?php if ($t['lignes']): ?>
<div class="bg-surface rounded-xl border border-outline-variant p-5 mb-6">
  <h3 class="font-headline-md font-bold mb-2">Liste d'ingrédients pour l'étiquette</h3>
  <p class="text-xs text-on-surface-variant mb-3">Ordre de poids décroissant, comme l'exige l'affichage.</p>
  <p class="text-sm bg-surface-container-low rounded-lg p-3"><?= h(liste_ingredients($produit_id)) ?></p>
</div>
<?php endif ?>

<form method="post" class="bg-surface rounded-xl border border-outline-variant p-5">
  <input type="hidden" name="produit_id" value="<?= $produit_id ?>">
  <h3 class="font-headline-md font-bold mb-4">Ingrédients</h3>

  <div id="lignes-recette" class="flex flex-col gap-3">
    <?php foreach ($lignes as $l): ?>
    <div class="ligne-recette border border-outline-variant rounded-xl p-3 flex flex-col gap-2">
      <div class="flex gap-2">
        <input type="text" name="libelle[]" value="<?= h($l['libelle']) ?>" placeholder="ex : Bœuf"
               class="flex-1 rounded-xl border-outline-variant text-sm">
        <input type="text" inputmode="decimal" name="quantite[]" value="<?= h((string)$l['quantite']) ?>"
               placeholder="kg" class="w-24 shrink-0 rounded-xl border-outline-variant text-sm">
        <button type="button" onclick="this.closest('.ligne-recette').remove()" aria-label="Retirer"
                class="material-symbols-outlined shrink-0 text-error min-w-[48px] min-h-[48px] rounded-full active:bg-error-container">delete</button>
      </div>
      <div class="flex gap-2 items-center">
        <select name="nature[]" class="flex-1 rounded-xl border-outline-variant text-sm">
          <?php foreach (LIB_NATURE as $k => $lib): ?>
          <option value="<?= $k ?>" <?= $l['nature'] === $k ? 'selected' : '' ?>><?= $lib ?></option>
          <?php endforeach ?>
        </select>
        <!-- Un navigateur n'envoie que les cases cochées : leurs index ne
             correspondraient plus aux lignes. On soumet donc un champ caché
             toujours présent, que la case se contente de piloter. -->
        <label class="flex items-center gap-2 text-sm font-semibold shrink-0 px-3">
          <input type="hidden" name="bio[]" value="<?= $l['bio'] ? '1' : '0' ?>" data-bio-valeur>
          <input type="checkbox" <?= $l['bio'] ? 'checked' : '' ?> class="rounded" data-bio-case>
          Bio
        </label>
      </div>
    </div>
    <?php endforeach ?>
  </div>

  <button type="button" id="btn_ligne_recette"
          class="mt-3 min-h-[48px] w-full sm:w-auto sm:px-5 rounded-full bg-surface-container-low text-primary font-semibold text-sm flex items-center justify-center gap-2 active:bg-surface-container">
    <span class="material-symbols-outlined">add_circle</span>Ajouter un ingrédient
  </button>

  <p class="text-xs text-on-surface-variant mt-4">
    Décochez « Bio » pour un ingrédient conventionnel. Attention : un ingrédient agricole non
    biologique n'est admis dans un produit revendiqué bio que s'il figure sur la liste
    autorisée — à vérifier avec votre organisme certificateur.
  </p>

  <button type="submit" name="save" value="1"
          class="barre-envoi mt-5 w-full bg-primary text-on-primary rounded-full py-4 font-bold text-base active:scale-[.99] transition">
    Enregistrer la composition
  </button>
</form>

<script>
// La case pilote le champ caché, seul réellement soumis.
function brancherBio(zone) {
  zone.querySelectorAll('[data-bio-case]').forEach(function (c) {
    if (c.dataset.branche) return;
    c.dataset.branche = '1';
    var champ = c.parentNode.querySelector('[data-bio-valeur]');
    c.addEventListener('change', function () { champ.value = c.checked ? '1' : '0'; });
  });
}
brancherBio(document);

document.getElementById('btn_ligne_recette').addEventListener('click', function () {
  var zone = document.getElementById('lignes-recette');
  var neuve = zone.querySelector('.ligne-recette').cloneNode(true);
  neuve.querySelectorAll('input[type=text]').forEach(function (i) { i.value = ''; });
  var c = neuve.querySelector('input[type=checkbox]');
  if (c) c.checked = true;
  var s = neuve.querySelector('select');
  if (s) s.selectedIndex = 0;
  var cache = neuve.querySelector('[data-bio-valeur]');
  if (cache) cache.value = '1';
  neuve.querySelectorAll('[data-bio-case]').forEach(function (x) { x.removeAttribute('data-branche'); });
  neuve.querySelector('button').addEventListener('click', function () { neuve.remove(); });
  zone.appendChild(neuve);
  brancherBio(neuve);
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>

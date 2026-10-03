<?php
// ============================================================
//  Atelier — l'écran du boucher pendant la fabrication.
//
//  Trois étapes, toujours dans le même ordre, toujours visibles :
//  1. j'ouvre un lot (le numéro existe → je peux étiqueter)
//  2. j'étiquette, autant de fois qu'il y a de barquettes
//  3. je clôture, quand je connais enfin le poids produit
//
//  Plusieurs lots cohabitent : c'est le quotidien. Le numéro suit la
//  règle du code du jour + occurrence, déjà en place.
// ============================================================
$page_active = 'atelier';
$page_title  = 'Atelier';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/atelier.php';
$moi = exiger_connexion();

$pdo = db();
$msg = $_SESSION['atelier_msg'] ?? ''; unset($_SESSION['atelier_msg']);
$err = $_SESSION['atelier_err'] ?? ''; unset($_SESSION['atelier_err']);

if (!atelier_installe()) {
    require __DIR__ . '/includes/header.php'; ?>
    <h2 class="font-headline-lg text-2xl font-bold text-primary mb-1">Atelier</h2>
    <div class="bg-error-container text-on-error-container rounded-xl p-5 text-sm mt-4">
      <p class="font-bold mb-1">Migration 007 pas encore appliquée</p>
      <p>Lancez-la depuis <a href="maj.php" class="underline font-semibold">Mise à jour de la base</a>.
         En attendant, les fabrications se saisissent comme avant depuis
         <a href="fabrications.php" class="underline font-semibold">Fabrications</a>.</p>
    </div>
    <?php require __DIR__ . '/includes/footer.php'; exit;
}

// ── Étape 1 : ouvrir un lot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ouvrir'])) {
    // Deux façons de choisir : les gros boutons ou la liste complète.
    // La liste l'emporte si elle est renseignée ; le JavaScript remet
    // l'autre à zéro pour qu'on ne puisse pas les contredire.
    $produit_id = (int)($_POST['produit_liste'] ?? 0) ?: (int)($_POST['produit_choix'] ?? 0);
    $produit    = trim($_POST['produit'] ?? '');
    $ref = $produit_id && referentiel_produits_pret() ? produit_par_id($produit_id) : null;
    if ($ref) { $produit = $ref['libelle']; } else { $produit_id = 0; }

    $date_fab = trim($_POST['date_fabrication'] ?? '') ?: date('Y-m-d');
    $liens = [];
    foreach ((array)($_POST['entree_id'] ?? []) as $eid) {
        $eid = (int)$eid;
        if ($eid) { $liens[$eid] = null; }   // la quantité utilisée se précise à la clôture
    }

    if ($produit === '')      { $err = 'Choisissez le produit.'; }
    elseif (!$liens)          { $err = 'Indiquez au moins une matière première utilisée.'; }
    else {
        try {
            // Conditionnement, conservation et DLC viennent du référentiel :
            // ce sont des caractéristiques du produit, pas des questions à
            // poser au tablier.
            $defauts = [];
            if ($ref) {
                $defauts['conditionnement'] = $ref['conditionnement'] ?: 'barquette';
                $defauts['conservation']    = $ref['conservation'] ?: 'froid_positif';
                if (!empty($ref['dlc_jours'])) {
                    $defauts['dlc'] = date('Y-m-d', strtotime($date_fab . ' +' . (int)$ref['dlc_jours'] . ' days'));
                }
            }
            $lot = ouvrir_lot($date_fab, $produit, $produit_id ?: null, $liens, $moi, $defauts);
            $_SESSION['atelier_msg'] = 'ouvert:' . $lot['id'];
            header('Location: atelier.php'); exit;
        } catch (Throwable $e) {
            error_log('Ouverture de lot : ' . $e->getMessage());
            $err = "Ouverture impossible : " . $e->getMessage();
        }
    }
}

// ── Étape 3 : clôturer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cloturer'])) {
    $id   = (int)$_POST['lot_id'];
    $qte  = nombre($_POST['quantite'] ?? '');
    $unite = trim($_POST['unite'] ?? 'kg') ?: 'kg';
    $dlc  = trim($_POST['dlc'] ?? '');

    if ($qte === null || $qte <= 0) {
        $err = 'Indiquez la quantité produite.';
    } elseif (!cloturer_lot($id, $qte, $unite, $dlc ?: null, $moi)) {
        // rowCount à zéro : quelqu'un d'autre est passé avant.
        $err = "Ce lot vient d'être clôturé par quelqu'un d'autre. Rien n'a été modifié.";
    } else {
        $_SESSION['atelier_msg'] = 'cloture';
        header('Location: atelier.php'); exit;
    }
}

$lots = lots_ouverts();
$ouvert_a_afficher = str_starts_with($msg, 'ouvert:') ? (int)substr($msg, 7) : 0;

// Produits les plus fabriqués, pour les gros boutons de l'étape 1.
$frequents = [];
if (referentiel_produits_pret()) {
    $frequents = $pdo->query(
        'SELECT p.id, p.libelle, p.libelle_court, COUNT(s.id) AS n
           FROM produits p LEFT JOIN lots_sortie s ON s.produit_id = p.id
          WHERE p.actif = 1
          GROUP BY p.id, p.libelle, p.libelle_court
          ORDER BY n DESC, p.ordre, p.libelle LIMIT 6'
    )->fetchAll();
    $tous = produits_actifs();
} else { $tous = []; }

// Lots d'entrée encore disponibles, le plus ancien d'abord : on écoule
// la matière dans l'ordre où elle est arrivée.
$entrees = $pdo->query(
    'SELECT e.id, e.num_lot, e.date_entree, e.fournisseur, t.libelle AS type_libelle
       FROM lots_entree e JOIN types_matiere t ON t.id = e.type_id
      ORDER BY e.date_entree DESC, e.id DESC LIMIT 40'
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="flex items-center justify-between mb-1">
  <h2 class="font-headline-lg text-2xl font-bold text-primary">Atelier</h2>
  <a href="import_etiquettes.php" class="text-sm text-primary underline">Importer des étiquettes</a>
</div>
<p class="text-sm text-on-surface-variant mb-4">
  <?= count($lots) ?> lot(s) ouvert(s) · <?= h(date('d/m/Y')) ?>
</p>

<?php if ($err): ?>
<div class="bg-error-container text-on-error-container rounded-xl p-4 mb-4 text-sm"><?= h($err) ?></div>
<?php endif ?>

<?php if ($msg === 'cloture'): ?>
<div class="bg-primary-container text-on-primary-container rounded-xl p-4 mb-4 text-sm">
  Lot clôturé. Il est entré au <a href="fabrications.php" class="underline font-semibold">registre</a>.
</div>
<?php endif ?>

<?php
// Le lot qui vient d'être ouvert : son numéro en très gros, avec
// l'étiquette juste dessous. C'est l'instant où le boucher en a besoin.
if ($ouvert_a_afficher):
    $nouveau = sortie_par_id($ouvert_a_afficher);
    if ($nouveau): ?>
<div class="bg-primary-container text-on-primary-container rounded-xl p-6 mb-5 text-center">
  <div class="text-sm font-semibold mb-1">Lot ouvert</div>
  <div class="lot-badge text-4xl font-extrabold tracking-wider my-2"><?= h($nouveau['num_lot']) ?></div>
  <div class="text-sm mb-4"><?= h($nouveau['produit']) ?></div>
  <a href="etiquette.php?type=sortie&id=<?= (int)$nouveau['id'] ?>" target="_blank"
     class="inline-flex items-center gap-2 bg-surface text-on-surface rounded-full px-6 py-3 font-bold">
    <span class="material-symbols-outlined">print</span>Étiquette
  </a>
</div>
<?php endif; endif ?>

<!-- ─────────── ÉTAPE 1 ─────────── -->
<section class="bg-surface rounded-xl border border-outline-variant mb-5">
  <details <?= $lots ? '' : 'open' ?>>
    <summary class="cursor-pointer select-none px-5 py-4 flex items-center gap-3">
      <span class="bg-primary text-on-primary rounded-full w-8 h-8 flex items-center justify-center font-bold shrink-0">1</span>
      <span class="font-headline-md font-bold flex-1">Ouvrir un lot</span>
      <span class="material-symbols-outlined text-on-surface-variant">expand_more</span>
    </summary>
    <form method="post" class="px-5 pb-5">
      <input type="hidden" name="date_fabrication" value="<?= h(date('Y-m-d')) ?>">

      <p class="text-xs font-bold uppercase tracking-wide text-on-surface-variant mb-2">Quel produit ?</p>
      <?php if ($frequents): ?>
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mb-3">
        <?php foreach ($frequents as $p): ?>
        <label class="choix-produit rounded-xl border-2 border-outline-variant p-3 cursor-pointer text-center">
          <input type="radio" name="produit_choix" value="<?= (int)$p['id'] ?>" class="sr-only">
          <span class="text-sm font-bold leading-tight block"><?= h($p['libelle_court'] ?: $p['libelle']) ?></span>
        </label>
        <?php endforeach ?>
      </div>
      <?php endif ?>
      <?php if ($tous): ?>
      <select name="produit_liste" id="produit-liste" class="w-full rounded-xl border-outline-variant mb-4">
        <option value="">— autre produit du référentiel —</option>
        <?php foreach ($tous as $p): ?>
        <option value="<?= (int)$p['id'] ?>"><?= h($p['libelle']) ?></option>
        <?php endforeach ?>
      </select>
      <?php else: ?>
      <input type="text" name="produit" placeholder="Nom du produit" class="w-full rounded-xl border-outline-variant mb-4">
      <?php endif ?>

      <p class="text-xs font-bold uppercase tracking-wide text-on-surface-variant mb-2">Avec quelle matière première ?</p>
      <?php if (!$entrees): ?>
      <p class="text-sm text-error mb-4">
        Aucun lot d'entrée enregistré. <a href="entrees.php" class="underline font-semibold">Saisissez une réception</a> d'abord.
      </p>
      <?php else: ?>
      <div class="border border-outline-variant rounded-xl divide-y divide-outline-variant max-h-64 overflow-y-auto mb-4">
        <?php foreach ($entrees as $e): ?>
        <label class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-surface-container-low">
          <input type="checkbox" name="entree_id[]" value="<?= (int)$e['id'] ?>" class="rounded border-outline-variant text-primary">
          <span class="flex-1 min-w-0">
            <span class="lot-badge text-sm font-bold"><?= h($e['num_lot']) ?></span>
            <span class="block text-xs text-on-surface-variant truncate">
              <?= h($e['type_libelle']) ?> · <?= h($e['fournisseur']) ?> · <?= fmt_date($e['date_entree']) ?>
            </span>
          </span>
        </label>
        <?php endforeach ?>
      </div>
      <?php endif ?>

      <button name="ouvrir" value="1" class="w-full bg-primary text-on-primary rounded-full py-4 font-bold text-base">
        Ouvrir le lot
      </button>
      <p class="text-xs text-on-surface-variant mt-2 text-center">
        Le poids produit, la DLC et la photo se renseignent à la clôture.
      </p>
    </form>
  </details>
</section>

<!-- ─────────── ÉTAPE 2 ─────────── -->
<div class="flex items-center gap-3 mb-3">
  <span class="bg-primary text-on-primary rounded-full w-8 h-8 flex items-center justify-center font-bold shrink-0">2</span>
  <span class="font-headline-md font-bold flex-1">Étiqueter</span>
</div>

<?php if (!$lots): ?>
<p class="text-sm text-on-surface-variant bg-surface rounded-xl border border-outline-variant p-5 mb-6">
  Aucun lot ouvert. Ouvrez-en un à l'étape 1 et son numéro apparaîtra ici.
</p>
<?php else: ?>
<div class="flex flex-col gap-3 mb-6" id="liste-lots">
  <?php foreach ($lots as $l): ?>
  <div class="bg-surface rounded-xl border <?= $l['du_jour'] ? 'border-outline-variant' : 'border-secondary' ?> overflow-hidden">
    <?php if (!$l['du_jour']): ?>
    <div class="bg-secondary-container text-on-secondary-container px-4 py-1.5 text-xs font-semibold">
      Resté ouvert depuis le <?= fmt_date($l['date_fabrication']) ?>
    </div>
    <?php endif ?>
    <div class="p-4">
      <div class="flex items-start justify-between gap-3 mb-3">
        <div class="min-w-0">
          <div class="lot-badge text-xl font-extrabold text-primary"><?= h($l['num_lot']) ?></div>
          <div class="text-sm font-semibold truncate"><?= h($l['produit']) ?></div>
          <div class="text-xs text-on-surface-variant">
            ouvert <?= $l['ouvert_le'] ? 'à ' . h(date('H\hi', strtotime($l['ouvert_le']))) : '' ?>
            <?= $l['ouvert_par'] ? 'par ' . h($l['ouvert_par']) : '' ?>
            · <?= count($l['sources']) ?> matière(s)
            <?php if ($l['etiq']): ?>
            · <strong><?= count($l['etiq']) ?> étiquette(s)</strong> · <?= fmt_qte($l['poids_etiq']) ?>
            <?php endif ?>
          </div>
        </div>
        <a href="etiquette.php?type=sortie&id=<?= (int)$l['id'] ?>" target="_blank"
           class="bg-primary text-on-primary rounded-full px-5 py-3 font-bold text-sm flex items-center gap-2 shrink-0">
          <span class="material-symbols-outlined">print</span>Étiquette
        </a>
      </div>

      <!-- ÉTAPE 3, repliée sous chaque lot : on clôture là où on est -->
      <details class="border-t border-outline-variant pt-3">
        <summary class="cursor-pointer select-none text-sm font-semibold flex items-center gap-2 text-on-surface-variant">
          <span class="bg-surface-container-high rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold">3</span>
          Clôturer ce lot
        </summary>
        <form method="post" class="mt-3 grid sm:grid-cols-3 gap-3">
          <input type="hidden" name="lot_id" value="<?= (int)$l['id'] ?>">
          <div>
            <label class="block text-xs font-semibold mb-1">Quantité produite</label>
            <div class="flex gap-1">
              <input type="text" inputmode="decimal" name="quantite" required
                     value="<?= $l['poids_etiq'] > 0 ? h(number_format($l['poids_etiq'], 3, ',', '')) : '' ?>"
                     class="flex-1 rounded-xl border-outline-variant">
              <select name="unite" class="rounded-xl border-outline-variant w-20">
                <?php foreach (['kg', 'u', 'L'] as $u): ?>
                <option value="<?= $u ?>" <?= ($l['unite'] ?? 'kg') === $u ? 'selected' : '' ?>><?= $u ?></option>
                <?php endforeach ?>
              </select>
            </div>
            <?php if ($l['poids_etiq'] > 0): ?>
            <p class="text-xs text-primary mt-1">Somme des étiquettes importées.</p>
            <?php endif ?>
          </div>
          <div>
            <label class="block text-xs font-semibold mb-1">DLC / DDM</label>
            <input type="date" name="dlc" value="<?= h($l['dlc'] ?? '') ?>" class="w-full rounded-xl border-outline-variant">
          </div>
          <div class="flex items-end">
            <button name="cloturer" value="1" class="w-full bg-primary text-on-primary rounded-full py-3 font-bold text-sm">
              Clôturer
            </button>
          </div>
        </form>
      </details>
    </div>
  </div>
  <?php endforeach ?>
</div>
<?php endif ?>

<p class="text-xs text-on-surface-variant">
  <a href="fabrications.php" class="text-primary underline">Registre des fabrications</a> —
  les lots clôturés y sont consignés.
</p>

<script>
// Un seul produit à la fois : choisir dans la liste efface le bouton
// coché, et inversement.
(function () {
  var liste = document.getElementById('produit-liste');
  var boutons = Array.prototype.slice.call(document.querySelectorAll('.choix-produit'));

  function peindre() {
    boutons.forEach(function (b) {
      var coche = b.querySelector('input').checked;
      b.classList.toggle('border-primary', coche);
      b.classList.toggle('bg-primary-container', coche);
      b.classList.toggle('border-outline-variant', !coche);
    });
  }
  boutons.forEach(function (b) {
    b.querySelector('input').addEventListener('change', function () {
      if (liste) { liste.value = ''; }
      peindre();
    });
  });
  liste?.addEventListener('change', function () {
    if (this.value !== '') {
      boutons.forEach(function (b) { b.querySelector('input').checked = false; });
    }
    peindre();
  });
  peindre();
})();

// Deux personnes travaillent dans l'atelier. Toutes les 15 secondes, on
// demande qui est ouvert ; si la liste a changé, on prévient sans
// recharger la page sous les doigts de celui qui est en train de saisir.
(function () {
  var connus = <?= json_encode(array_map(fn($l) => $l['num_lot'], $lots), JSON_UNESCAPED_UNICODE) ?>;
  var bandeau = null;

  function annoncer(nouveaux) {
    if (bandeau) bandeau.remove();
    bandeau = document.createElement('div');
    bandeau.className = 'fixed left-3 right-3 bottom-24 z-50 bg-secondary-container text-on-secondary-container ' +
                        'rounded-xl px-4 py-3 text-sm shadow-lg flex items-center gap-3';
    bandeau.innerHTML = '<span class="flex-1">' + nouveaux.join(', ') +
                        ' vient d\'être ouvert par un collègue.</span>' +
                        '<button class="font-bold underline shrink-0">Afficher</button>';
    bandeau.querySelector('button').addEventListener('click', function () { location.reload(); });
    document.body.appendChild(bandeau);
  }

  setInterval(function () {
    fetch('atelier_etat.php', { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d || !Array.isArray(d.lots)) return;
        var nouveaux = d.lots.filter(function (n) { return connus.indexOf(n) === -1; });
        if (nouveaux.length) { connus = d.lots; annoncer(nouveaux); }
      })
      .catch(function () { /* réseau capricieux en atelier : on réessaie au tour suivant */ });
  }, 15000);
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
$page_active = 'fabrications';
$page_title  = 'Fabrications';
require_once __DIR__ . '/includes/auth.php';
$moi = exiger_connexion();

$pdo    = db();
$action = $_GET['action'] ?? 'liste';
$id     = (int)($_GET['id'] ?? 0);
$msg = '';

// ── Enregistrement (création / modification)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $date_f  = trim($_POST['date_fabrication'] ?? '');

    // Produit : soit choisi dans le référentiel, soit saisi librement tant qu'il est vide.
    $produit_id = (int)($_POST['produit_id'] ?? 0);
    $produit    = trim($_POST['produit'] ?? '');
    if ($produit_id) {
        $ref = referentiel_produits_pret() ? produit_par_id($produit_id) : null;
        if (!$ref) { $produit_id = 0; }
        else { $produit = $ref['libelle']; }   // libellé figé dans le registre
    }
    $qte     = nombre($_POST['quantite'] ?? '');
    $unite   = trim($_POST['unite'] ?? 'kg') ?: 'kg';
    $cond    = $_POST['conditionnement'] ?? '';
    $consv   = $_POST['conservation'] ?? '';
    $dlc     = trim($_POST['dlc'] ?? '');
    $ct      = nombre($_POST['cuisson_temp'] ?? '');
    $cd      = trim($_POST['cuisson_duree'] ?? '');
    $refr    = trim($_POST['refroidissement'] ?? '');
    $notes   = trim($_POST['notes'] ?? '');
    $edit_id = (int)($_POST['id'] ?? 0);

    // Lots d'entrée utilisés
    $ids_in  = $_POST['entree_id']  ?? [];
    $qtes_in = $_POST['entree_qte'] ?? [];
    $liens = [];
    foreach ($ids_in as $i => $eid) {
        $eid = (int)$eid;
        if (!$eid || isset($liens[$eid])) continue;
        $liens[$eid] = nombre($qtes_in[$i] ?? '');
    }

    $err = [];
    if (!$date_f)                       $err[] = 'la date';
    if ($produit === '')                $err[] = 'le nom du produit';
    if ($qte === null || $qte <= 0)     $err[] = 'la quantité fabriquée';
    if (!isset(LIB_CONDITIONNEMENT[$cond])) $err[] = 'le conditionnement';
    if (!isset(LIB_CONSERVATION[$consv]))   $err[] = 'la conservation';
    if (!$liens)                        $err[] = 'au moins un lot d\'entrée utilisé';

    if ($err) {
        $msg = 'Champs obligatoires manquants : ' . implode(', ', $err) . '.';
    } else {
        // Comme pour les entrées : un échec de photo ne doit pas faire perdre la saisie.
        $res_photo = enregistrer_photo($_FILES['photo'] ?? null, $_POST['photo_data'] ?? null, 'fabrications');
        $photo = $res_photo['chemin'];
        if ($res_photo['erreur']) $_SESSION['photo_erreur'] = $res_photo['erreur'];

        try {
            $pdo->beginTransaction();
            if ($edit_id) {
                $anc = sortie_par_id($edit_id);
                if (!$photo) $photo = $anc['photo'] ?? null;
                $pdo->prepare(
                    'UPDATE lots_sortie SET date_fabrication=?, produit=?, produit_id=?, quantite=?, unite=?,
                     conditionnement=?, conservation=?, dlc=?, cuisson_temp=?, cuisson_duree=?,
                     refroidissement=?, photo=?, notes=? WHERE id=?'
                )->execute([$date_f, $produit, $produit_id ?: null, $qte, $unite, $cond, $consv, $dlc ?: null,
                            $ct, $cd ?: null, $refr ?: null, $photo, $notes ?: null, $edit_id]);
                $sortie_id = $edit_id;
                $pdo->prepare('DELETE FROM lots_sortie_entrees WHERE sortie_id=?')->execute([$sortie_id]);
            } else {
                $num = generer_num_sortie($date_f);
                $pdo->prepare(
                    'INSERT INTO lots_sortie
                     (num_lot, date_fabrication, produit, produit_id, quantite, unite, conditionnement, conservation,
                      dlc, cuisson_temp, cuisson_duree, refroidissement, photo, notes, cree_par)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([$num, $date_f, $produit, $produit_id ?: null, $qte, $unite, $cond, $consv, $dlc ?: null,
                            $ct, $cd ?: null, $refr ?: null, $photo, $notes ?: null, $moi['nom']]);
                $sortie_id = (int)$pdo->lastInsertId();
            }

            $ins = $pdo->prepare('INSERT INTO lots_sortie_entrees (sortie_id, entree_id, quantite_kg) VALUES (?,?,?)');
            foreach ($liens as $eid => $q_kg) $ins->execute([$sortie_id, $eid, $q_kg]);

            $pdo->commit();
            header('Location: fabrications.php?action=voir&id=' . $sortie_id . '&msg=' . ($edit_id ? 'maj' : 'cree'));
            exit;
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $msg = "Erreur d'enregistrement : " . $ex->getMessage();
        }
    }
    $action = $edit_id ? 'modifier' : 'nouveau';
    $id     = $edit_id;
}

// ── Suppression
if ($action === 'supprimer' && $id) {
    $pdo->prepare('DELETE FROM lots_sortie WHERE id=?')->execute([$id]);
    header('Location: fabrications.php?msg=suppr');
    exit;
}

$produits = $pdo->query('SELECT DISTINCT produit FROM lots_sortie ORDER BY produit')->fetchAll(PDO::FETCH_COLUMN);

$flashes = [
    'cree'  => 'Lot de fabrication créé.',
    'maj'   => 'Fabrication mise à jour.',
    'suppr' => 'Fabrication supprimée.',
];
$flash = $flashes[$_GET['msg'] ?? ''] ?? '';

require __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?>
<div class="bg-error-container text-on-error-container rounded-xl px-4 py-3 mb-4 text-sm"><?= h($msg) ?></div>
<?php endif ?>
<?php if ($flash): ?>
<div class="bg-primary-container text-on-primary-container rounded-xl px-4 py-3 mb-4 text-sm"><?= h($flash) ?></div>
<?php endif ?>
<?php if (!empty($_SESSION['photo_erreur'])): ?>
<div class="bg-error-container text-on-error-container rounded-xl px-4 py-3 mb-4 text-sm">
  <strong>La fabrication est bien enregistrée, mais la photo n'a pas pu être jointe.</strong><br>
  <?= h($_SESSION['photo_erreur']) ?> Vous pouvez la rajouter en modifiant la fiche.
</div>
<?php unset($_SESSION['photo_erreur']); endif ?>

<?php
// ============================================================
//  FORMULAIRE
// ============================================================
if ($action === 'nouveau' || $action === 'modifier'):
    $s = $id ? sortie_par_id($id) : null;
    $v = function (string $k, string $d = '') use ($s) {
        return h((string)($_POST[$k] ?? $s[$k] ?? $d));
    };

    // Catalogue produits : liste fermée dès qu'au moins un produit existe,
    // saisie libre tant que le référentiel n'est pas alimenté.
    $catalogue = referentiel_produits_pret() ? produits_actifs() : [];
    $infos_produits = [];
    foreach ($catalogue as $p) {
        $infos_produits[(string)$p['id']] = [
            'dlc'   => $p['dlc_jours'] === null ? null : (int)$p['dlc_jours'],
            'cond'  => $p['conditionnement'] ?? '',
            'consv' => $p['conservation'] ?? '',
        ];
    }

    // Lots d'entrée disponibles pour le sélecteur
    $dispo = $pdo->query(
        'SELECT e.id, e.num_lot, e.date_entree, e.fournisseur, t.code, t.libelle AS type_libelle
         FROM lots_entree e JOIN types_matiere t ON t.id=e.type_id
         ORDER BY e.date_entree DESC, e.id DESC LIMIT 400'
    )->fetchAll();

    // Sélection en cours (repost, ou édition)
    $sel = [];
    if (!empty($_POST['entree_id'])) {
        foreach ($_POST['entree_id'] as $i => $eid) {
            if ((int)$eid) $sel[] = ['id' => (int)$eid, 'qte' => $_POST['entree_qte'][$i] ?? ''];
        }
    } elseif ($s) {
        foreach (entrees_de_sortie($id) as $e) {
            $sel[] = ['id' => (int)$e['id'], 'qte' => $e['qte_utilisee'] === null ? '' : rtrim(rtrim($e['qte_utilisee'], '0'), '.')];
        }
    }
    if (!$sel) $sel[] = ['id' => 0, 'qte' => ''];
?>
<div class="flex items-center gap-3 mb-6">
  <a href="fabrications.php" class="material-symbols-outlined text-on-surface-variant hover:bg-surface-container p-2 rounded-full">arrow_back</a>
  <h2 class="font-headline-lg text-2xl font-bold text-primary"><?= $s ? 'Modifier ' . h($s['num_lot']) : 'Nouvelle fabrication' ?></h2>
</div>

<form method="post" enctype="multipart/form-data" class="bg-surface rounded-xl border border-outline-variant p-5 flex flex-col gap-5">
  <input type="hidden" name="id" value="<?= (int)$id ?>">

  <div class="grid sm:grid-cols-2 gap-4">
    <div>
      <label class="block text-sm font-semibold mb-1">Date de fabrication <span class="text-error">*</span></label>
      <input type="date" name="date_fabrication" required value="<?= $v('date_fabrication', date('Y-m-d')) ?>"
             class="w-full rounded-xl border-outline-variant">
    </div>
    <div>
      <label class="block text-sm font-semibold mb-1">Produit <span class="text-error">*</span></label>
      <?php if ($catalogue): ?>
      <select name="produit_id" required class="w-full rounded-xl border-outline-variant"
              data-produits='<?= h(json_encode($infos_produits, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'>
        <option value="">— choisir —</option>
        <?php $sp = (int)($_POST['produit_id'] ?? $s['produit_id'] ?? 0); foreach ($catalogue as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= $sp === (int)$p['id'] ? 'selected' : '' ?>><?= h($p['libelle']) ?></option>
        <?php endforeach ?>
      </select>
      <p class="text-xs text-on-surface-variant mt-1">
        Liste gérée dans <a class="text-primary underline" href="produits.php">Produits</a>.
      </p>
      <?php else: ?>
      <input type="text" name="produit" required list="dl_prod" placeholder="ex : Merguez"
             value="<?= $v('produit') ?>" class="w-full rounded-xl border-outline-variant">
      <datalist id="dl_prod"><?php foreach ($produits as $p): ?><option value="<?= h($p) ?>"><?php endforeach ?></datalist>
      <?php endif ?>
    </div>
  </div>

  <div>
    <label class="block text-sm font-semibold mb-1">Quantité fabriquée <span class="text-error">*</span></label>
    <div class="flex gap-2">
      <input type="text" inputmode="decimal" name="quantite" required placeholder="ex : 24,5"
             value="<?= $v('quantite') ?>" class="flex-1 rounded-xl border-outline-variant">
      <select name="unite" class="rounded-xl border-outline-variant w-32">
        <?php $su = $_POST['unite'] ?? $s['unite'] ?? 'kg'; foreach (['kg', 'pièces', 'barquettes', 'L'] as $u): ?>
        <option value="<?= $u ?>" <?= $su === $u ? 'selected' : '' ?>><?= $u ?></option>
        <?php endforeach ?>
      </select>
    </div>
  </div>

  <!-- Lots d'entrée utilisés -->
  <div class="border-t border-outline-variant pt-4" data-lots>
    <label class="block text-sm font-semibold mb-2">Lots d'entrée utilisés <span class="text-error">*</span></label>
    <script type="application/json" id="lots-dispo"><?= json_encode(array_map(function ($d) {
        return ['id' => (int)$d['id'], 'num' => $d['num_lot'], 'type' => $d['type_libelle'],
                'fourn' => $d['fournisseur'], 'date' => fmt_date($d['date_entree'])];
    }, $dispo), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <div id="lignes" class="flex flex-col gap-2">
      <?php foreach ($sel as $ligne): ?>
      <div class="ligne flex gap-2 items-center">
        <select name="entree_id[]" class="flex-1 rounded-xl border-outline-variant text-sm">
          <option value="">— choisir un lot —</option>
          <?php foreach ($dispo as $d): ?>
          <option value="<?= (int)$d['id'] ?>" <?= $ligne['id'] == $d['id'] ? 'selected' : '' ?>>
            <?= h($d['num_lot']) ?> — <?= h($d['type_libelle']) ?> — <?= h($d['fournisseur']) ?> (<?= fmt_date($d['date_entree']) ?>)
          </option>
          <?php endforeach ?>
        </select>
        <input type="text" inputmode="decimal" name="entree_qte[]" placeholder="kg" value="<?= h((string)$ligne['qte']) ?>"
               class="w-20 shrink-0 rounded-xl border-outline-variant text-sm">
        <button type="button" onclick="this.closest('.ligne').remove()" aria-label="Retirer ce lot"
                class="material-symbols-outlined shrink-0 text-error min-w-[48px] min-h-[48px] rounded-full active:bg-error-container">delete</button>
      </div>
      <?php endforeach ?>
    </div>
    <button type="button" id="btn_ajout"
            class="mt-3 min-h-[48px] w-full sm:w-auto sm:px-5 rounded-full bg-surface-container-low text-primary font-semibold text-sm flex items-center justify-center gap-2 active:bg-surface-container">
      <span class="material-symbols-outlined">add_circle</span>Ajouter un lot
    </button>
    <p class="text-xs text-on-surface-variant mt-2">La quantité en kg est facultative : elle sert au suivi de consommation des lots d'entrée.</p>
  </div>

  <div class="grid sm:grid-cols-2 gap-4 border-t border-outline-variant pt-4">
    <div>
      <label class="block text-sm font-semibold mb-2">Conditionnement <span class="text-error">*</span></label>
      <div class="flex flex-col gap-2">
        <?php $sc = $_POST['conditionnement'] ?? $s['conditionnement'] ?? ''; foreach (LIB_CONDITIONNEMENT as $k => $l): ?>
        <label class="cursor-pointer">
          <input type="radio" name="conditionnement" value="<?= $k ?>" class="peer sr-only" <?= $sc === $k ? 'checked' : '' ?> required>
          <span class="block px-4 py-3 rounded-xl border-2 border-outline-variant peer-checked:border-primary peer-checked:bg-primary-container peer-checked:text-on-primary-container text-sm font-medium"><?= $l ?></span>
        </label>
        <?php endforeach ?>
      </div>
    </div>
    <div>
      <label class="block text-sm font-semibold mb-2">Conservation <span class="text-error">*</span></label>
      <div class="flex flex-col gap-2">
        <?php $sv = $_POST['conservation'] ?? $s['conservation'] ?? ''; foreach (LIB_CONSERVATION as $k => $l): ?>
        <label class="cursor-pointer">
          <input type="radio" name="conservation" value="<?= $k ?>" class="peer sr-only" <?= $sv === $k ? 'checked' : '' ?> required>
          <span class="block px-4 py-3 rounded-xl border-2 border-outline-variant peer-checked:border-primary peer-checked:bg-primary-container peer-checked:text-on-primary-container text-sm font-medium"><?= $l ?></span>
        </label>
        <?php endforeach ?>
      </div>
    </div>
  </div>

  <details class="border-t border-outline-variant pt-4">
    <summary class="cursor-pointer text-sm font-semibold text-on-surface-variant">Champs complémentaires (facultatifs)</summary>
    <div class="grid sm:grid-cols-2 gap-4 mt-4">
      <div>
        <label class="block text-sm font-semibold mb-1">DLC / DDM</label>
        <input type="date" name="dlc" value="<?= $v('dlc') ?>" class="w-full rounded-xl border-outline-variant">
      </div>
      <div>
        <label class="block text-sm font-semibold mb-1">Cuisson — température (°C)</label>
        <input type="text" inputmode="decimal" name="cuisson_temp" value="<?= $v('cuisson_temp') ?>" class="w-full rounded-xl border-outline-variant">
      </div>
      <div>
        <label class="block text-sm font-semibold mb-1">Cuisson — durée</label>
        <input type="text" name="cuisson_duree" placeholder="ex : 45 min" value="<?= $v('cuisson_duree') ?>" class="w-full rounded-xl border-outline-variant">
      </div>
      <div>
        <label class="block text-sm font-semibold mb-1">Refroidissement</label>
        <input type="text" name="refroidissement" placeholder="ex : 63→10 °C en 1 h 30" value="<?= $v('refroidissement') ?>" class="w-full rounded-xl border-outline-variant">
      </div>
      <div class="sm:col-span-2">
        <label class="block text-sm font-semibold mb-1">Notes</label>
        <textarea name="notes" rows="2" class="w-full rounded-xl border-outline-variant"><?= $v('notes') ?></textarea>
      </div>
    </div>
  </details>

  <?php
  $photo_actuelle = $s['photo'] ?? null;
  $photo_aide = 'Étiquette, produit fini, fiche de cuisson…';
  require __DIR__ . '/includes/champ_photo.php';
  ?>

  <div class="bg-surface-container-low rounded-xl px-4 py-3 text-sm">
    <span class="text-on-surface-variant">N° de lot : </span>
    <strong class="lot-badge"><?= $s ? h($s['num_lot']) : 'généré automatiquement (ex. ' . h(reglage('prefixe_sortie')) . date('dmy') . '-1)' ?></strong>
  </div>

  <button type="submit" name="save" value="1"
          class="barre-envoi w-full bg-primary text-on-primary rounded-full py-4 font-bold text-base active:scale-[.99] transition">
    <?= $s ? 'Enregistrer les modifications' : 'Créer le lot de fabrication' ?>
  </button>
</form>


<?php
// ============================================================
//  DÉTAIL
// ============================================================
elseif ($action === 'voir' && $id):
    $s = sortie_par_id($id);
    if (!$s) { echo '<p>Fabrication introuvable.</p>'; require __DIR__ . '/includes/footer.php'; exit; }
    $sources = entrees_de_sortie($id);
?>
<div class="flex items-center gap-3 mb-6">
  <a href="fabrications.php" class="material-symbols-outlined text-on-surface-variant hover:bg-surface-container p-2 rounded-full">arrow_back</a>
  <div class="flex-1">
    <div class="lot-badge text-2xl font-bold text-primary"><?= h($s['num_lot']) ?></div>
    <div class="text-sm text-on-surface-variant"><?= h($s['produit']) ?> · <?= fmt_date($s['date_fabrication']) ?></div>
  </div>
  <a href="fabrications.php?action=modifier&id=<?= $id ?>" class="material-symbols-outlined text-primary hover:bg-surface-container p-2 rounded-full">edit</a>
  <a href="etiquette.php?type=sortie&id=<?= $id ?>" target="_blank" class="material-symbols-outlined text-primary hover:bg-surface-container p-2 rounded-full">print</a>
</div>

<div class="bg-surface rounded-xl border border-outline-variant p-5 mb-5">
  <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
    <?php
    $lignes = [
      'Quantité fabriquée' => fmt_qte((float)$s['quantite'], $s['unite']),
      'Conditionnement'    => LIB_CONDITIONNEMENT[$s['conditionnement']] ?? '—',
      'Conservation'       => LIB_CONSERVATION[$s['conservation']] ?? '—',
      'DLC / DDM'          => fmt_date($s['dlc']),
      'Cuisson'            => $s['cuisson_temp'] === null && !$s['cuisson_duree'] ? '—'
                              : trim(($s['cuisson_temp'] !== null ? fmt_temp((float)$s['cuisson_temp']) : '') . ' ' . h($s['cuisson_duree'])),
      'Refroidissement'    => h($s['refroidissement']) ?: '—',
      'Saisi par'          => h($s['cree_par']) ?: '—',
      'Saisi le'           => date('d/m/Y à H:i', strtotime($s['date_creation'])),
    ];
    foreach ($lignes as $k => $val): ?>
    <div><dt class="text-on-surface-variant text-xs uppercase tracking-wide"><?= $k ?></dt><dd class="font-semibold"><?= $val ?></dd></div>
    <?php endforeach ?>
  </dl>
  <?php if ($s['notes']): ?><p class="mt-4 text-sm bg-surface-container-low rounded-lg p-3"><?= nl2br(h($s['notes'])) ?></p><?php endif ?>
  <?php if ($s['photo']): ?>
  <a href="<?= UPLOAD_URL . h($s['photo']) ?>" target="_blank" class="inline-flex items-center gap-2 mt-4 text-primary text-sm font-semibold">
    <span class="material-symbols-outlined">photo_camera</span>Voir la photo</a>
  <?php endif ?>
</div>

<?php $org = origine_fabrication($id); ?>
<?php if ($org['melange']): ?>
<div class="bg-error-container text-on-error-container rounded-xl p-4 mb-5 text-sm">
  <div class="flex items-center gap-2 font-bold mb-1">
    <span class="material-symbols-outlined">warning</span>Origines différentes
  </div>
  Les lots utilisés ne partagent pas la même origine :
  <strong><?= h(implode(' · ', $org['origines'])) ?></strong>.
  Aucune mention d'origine unique ne peut être portée sur l'étiquette de cette fabrication.
</div>
<?php elseif ($org['incomplets']): ?>
<div class="bg-error-container text-on-error-container rounded-xl p-4 mb-5 text-sm">
  <div class="flex items-center gap-2 font-bold mb-1">
    <span class="material-symbols-outlined">warning</span>Origine incomplète
  </div>
  Mentions d'origine manquantes sur : <strong><?= h(implode(', ', $org['incomplets'])) ?></strong>.
  Complétez ces lots d'entrée avant d'étiqueter.
</div>
<?php elseif ($org['unique']): ?>
<div class="bg-surface rounded-xl border border-outline-variant p-4 mb-5 text-sm">
  <div class="text-xs text-on-surface-variant mb-1">Origine de cette fabrication</div>
  <div class="font-semibold">Origine : <?= h($org['unique']) ?></div>
  <div class="font-semibold"><?= h(mention_decoupe()) ?></div>
</div>
<?php endif ?>

<h3 class="font-headline-md font-bold mb-3">Lots d'entrée utilisés (<?= count($sources) ?>)</h3>
<div class="flex flex-col gap-2">
  <?php foreach ($sources as $e): ?>
  <a href="entrees.php?action=voir&id=<?= (int)$e['id'] ?>" class="bg-surface rounded-xl border border-outline-variant p-4 flex items-center gap-3 hover:bg-surface-container-low">
    <span class="lot-badge text-xs font-bold text-white px-2 py-1 rounded" style="background:<?= h($e['couleur']) ?>"><?= h($e['code']) ?></span>
    <div class="flex-1 min-w-0">
      <div class="lot-badge font-bold"><?= h($e['num_lot']) ?></div>
      <div class="text-sm text-on-surface-variant truncate"><?= h($e['fournisseur']) ?> · <?= fmt_date($e['date_entree']) ?></div>
    </div>
    <div class="text-sm font-semibold"><?= $e['qte_utilisee'] === null ? '' : fmt_qte((float)$e['qte_utilisee']) ?></div>
    <span class="material-symbols-outlined text-on-surface-variant">chevron_right</span>
  </a>
  <?php endforeach ?>
</div>

<a href="fabrications.php?action=supprimer&id=<?= $id ?>" onclick="return confirm('Supprimer définitivement cette fabrication ?')"
   class="inline-block mt-6 text-sm text-error font-semibold">Supprimer cette fabrication</a>

<?php
// ============================================================
//  LISTE
// ============================================================
else:
    $f_q = trim($_GET['q'] ?? '');
    $sql = 'SELECT s.*, (SELECT COUNT(*) FROM lots_sortie_entrees se WHERE se.sortie_id=s.id) AS nb_sources
            FROM lots_sortie s';
    $params = [];
    if ($f_q !== '') {
        $sql .= ' WHERE s.num_lot LIKE ? OR s.produit LIKE ?';
        $params = ["%$f_q%", "%$f_q%"];
    }
    $sql .= ' ORDER BY s.date_fabrication DESC, s.id DESC LIMIT 300';
    $q = $pdo->prepare($sql); $q->execute($params); $fabs = $q->fetchAll();
?>
<div class="flex items-center justify-between mb-5">
  <h2 class="font-headline-lg text-2xl font-bold text-primary">Fabrications</h2>
  <a href="fabrications.php?action=nouveau" class="bg-primary text-on-primary rounded-full px-5 py-3 font-bold text-sm flex items-center gap-2">
    <span class="material-symbols-outlined">add</span>Nouvelle
  </a>
</div>

<form method="get" class="flex gap-2 mb-5">
  <input type="text" name="q" value="<?= h($f_q) ?>" placeholder="N° de lot, produit…"
         class="flex-1 rounded-full border-outline-variant text-sm">
  <button class="bg-surface-container-high rounded-full px-4"><span class="material-symbols-outlined align-middle">search</span></button>
</form>

<?php if (!$fabs): ?>
<div class="bg-surface rounded-xl border border-outline-variant p-8 text-center text-on-surface-variant">
  <span class="material-symbols-outlined text-4xl">outbox</span>
  <p class="mt-2 text-sm">Aucune fabrication enregistrée.</p>
</div>
<?php else: ?>
<div class="flex flex-col gap-2">
  <?php foreach ($fabs as $f): ?>
  <a href="fabrications.php?action=voir&id=<?= (int)$f['id'] ?>" class="bg-surface rounded-xl border border-outline-variant p-4 flex items-center gap-3 hover:bg-surface-container-low">
    <div class="flex-1 min-w-0">
      <div class="lot-badge font-bold text-primary"><?= h($f['num_lot']) ?></div>
      <div class="text-sm truncate"><?= h($f['produit']) ?></div>
      <div class="text-xs text-on-surface-variant"><?= fmt_date($f['date_fabrication']) ?> · <?= (int)$f['nb_sources'] ?> lot(s) source</div>
    </div>
    <div class="text-right text-sm">
      <div class="font-semibold"><?= fmt_qte((float)$f['quantite'], $f['unite']) ?></div>
      <div class="text-xs text-on-surface-variant"><?= LIB_CONDITIONNEMENT[$f['conditionnement']] ?? '' ?> · <?= LIB_CONSERVATION[$f['conservation']] ?? '' ?></div>
    </div>
  </a>
  <?php endforeach ?>
</div>
<?php endif ?>

<?php endif ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

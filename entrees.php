<?php
$page_active = 'entrees';
$page_title  = 'Entrées matières premières';
require_once __DIR__ . '/includes/auth.php';
$moi = exiger_connexion();

$pdo    = db();
$action = $_GET['action'] ?? 'liste';
$id     = (int)($_GET['id'] ?? 0);
$msg = '';

// ── Enregistrement (création / modification)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $type_id = (int)($_POST['type_id'] ?? 0);
    $date_e  = trim($_POST['date_entree'] ?? '');
    $temp    = nombre($_POST['temperature'] ?? '');
    $fourn   = trim($_POST['fournisseur'] ?? '');
    $poids   = nombre($_POST['poids_kg'] ?? '');
    $forme   = ($_POST['forme'] ?? '') ?: null;
    $etat    = ($_POST['etat'] ?? '') ?: null;
    $numf    = trim($_POST['num_lot_fournisseur'] ?? '');
    $numa    = trim($_POST['num_animal'] ?? '');
    $o_n     = trim($_POST['pays_naissance'] ?? '');
    $o_e     = trim($_POST['pays_elevage'] ?? '');
    $o_a     = trim($_POST['pays_abattage'] ?? '');
    $o_ag    = trim($_POST['agrement_abattoir'] ?? '');
    $notes   = trim($_POST['notes'] ?? '');
    $edit_id = (int)($_POST['id'] ?? 0);

    $err = [];
    if (!$type_id)      $err[] = 'le type';
    if (!$date_e)       $err[] = 'la date';
    if ($temp === null) $err[] = 'la température';
    if ($fourn === '')  $err[] = 'le fournisseur';
    if ($forme !== null && !isset(LIB_FORME[$forme])) $forme = null;
    if ($etat  !== null && !isset(LIB_ETAT[$etat]))   $etat  = null;

    if ($err) {
        $msg = 'Champs obligatoires manquants : ' . implode(', ', $err) . '.';
    } else {
        // La photo ne doit jamais faire perdre la saisie : en cas d'échec on
        // enregistre quand même le lot et on prévient sur la fiche.
        $res_photo = enregistrer_photo($_FILES['photo'] ?? null, $_POST['photo_data'] ?? null, 'entrees');
        $photo = $res_photo['chemin'];
        if ($res_photo['erreur']) $_SESSION['photo_erreur'] = $res_photo['erreur'];

        try {
            if ($edit_id) {
                $anc = entree_par_id($edit_id);
                if (!$photo) $photo = $anc['photo'] ?? null;
                $pdo->prepare(
                    'UPDATE lots_entree SET type_id=?, date_entree=?, temperature=?, fournisseur=?,
                     poids_kg=?, photo=?, forme=?, etat=?, num_lot_fournisseur=?, notes=?,
                     num_animal=?, pays_naissance=?, pays_elevage=?, pays_abattage=?, agrement_abattoir=?
                     WHERE id=?'
                )->execute([$type_id, $date_e, $temp, $fourn, $poids, $photo, $forme, $etat,
                            $numf ?: null, $notes ?: null,
                            $numa ?: null, $o_n ?: null, $o_e ?: null, $o_a ?: null, $o_ag ?: null,
                            $edit_id]);
                header('Location: entrees.php?action=voir&id=' . $edit_id . '&msg=maj');
                exit;
            }
            $num = generer_num_entree($type_id, $date_e);
            $pdo->prepare(
                'INSERT INTO lots_entree
                 (num_lot, type_id, date_entree, temperature, fournisseur, poids_kg, photo, forme, etat,
                  num_lot_fournisseur, notes, cree_par,
                  num_animal, pays_naissance, pays_elevage, pays_abattage, agrement_abattoir)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([$num, $type_id, $date_e, $temp, $fourn, $poids, $photo, $forme, $etat,
                        $numf ?: null, $notes ?: null, $moi['nom'],
                        $numa ?: null, $o_n ?: null, $o_e ?: null, $o_a ?: null, $o_ag ?: null]);
            header('Location: entrees.php?action=voir&id=' . $pdo->lastInsertId() . '&msg=cree');
            exit;
        } catch (PDOException $ex) {
            $msg = "Erreur d'enregistrement : " . $ex->getMessage();
        }
    }
    $action = $edit_id ? 'modifier' : 'nouveau';
    $id     = $edit_id;
}

// ── Suppression
if ($action === 'supprimer' && $id) {
    $q = $pdo->prepare('SELECT COUNT(*) FROM lots_sortie_entrees WHERE entree_id=?');
    $q->execute([$id]);
    if ((int)$q->fetchColumn() > 0) {
        header('Location: entrees.php?action=voir&id=' . $id . '&msg=lie');
        exit;
    }
    $pdo->prepare('DELETE FROM lots_entree WHERE id=?')->execute([$id]);
    header('Location: entrees.php?msg=suppr');
    exit;
}

$types = types_actifs();
$fournisseurs = $pdo->query('SELECT DISTINCT fournisseur FROM lots_entree ORDER BY fournisseur')->fetchAll(PDO::FETCH_COLUMN);

$flashes = [
    'cree'  => "Lot d'entrée créé.",
    'maj'   => 'Lot mis à jour.',
    'suppr' => 'Lot supprimé.',
    'lie'   => 'Suppression impossible : ce lot est utilisé par une fabrication.',
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
  <strong>Le lot est bien enregistré, mais la photo n'a pas pu être jointe.</strong><br>
  <?= h($_SESSION['photo_erreur']) ?> Vous pouvez la rajouter en modifiant le lot.
</div>
<?php unset($_SESSION['photo_erreur']); endif ?>

<?php
// ============================================================
//  FORMULAIRE
// ============================================================
if ($action === 'nouveau' || $action === 'modifier'):
    $e = $id ? entree_par_id($id) : null;
    $v = function (string $k, string $d = '') use ($e) {
        return h((string)($_POST[$k] ?? $e[$k] ?? $d));
    };
?>
<div class="flex items-center gap-3 mb-6">
  <a href="entrees.php" class="material-symbols-outlined text-on-surface-variant hover:bg-surface-container p-2 rounded-full">arrow_back</a>
  <h2 class="font-headline-lg text-2xl font-bold text-primary"><?= $e ? "Modifier l'entrée " . h($e['num_lot']) : 'Nouvelle entrée' ?></h2>
</div>

<form method="post" enctype="multipart/form-data" class="bg-surface rounded-xl border border-outline-variant p-5 flex flex-col gap-5">
  <input type="hidden" name="id" value="<?= (int)$id ?>">

  <div>
    <label class="block text-sm font-semibold mb-2">Type de matière <span class="text-error">*</span></label>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
      <?php $sel_type = (int)($_POST['type_id'] ?? $e['type_id'] ?? 0); foreach ($types as $t): ?>
      <label class="cursor-pointer">
        <input type="radio" name="type_id" value="<?= (int)$t['id'] ?>" class="peer sr-only" <?= $sel_type == $t['id'] ? 'checked' : '' ?> required>
        <span class="block text-center px-3 py-3 rounded-xl border-2 border-outline-variant peer-checked:border-primary peer-checked:bg-primary-container peer-checked:text-on-primary-container text-sm font-medium">
          <span class="lot-badge font-bold block"><?= h($t['code']) ?></span>
          <span class="text-xs opacity-80"><?= h($t['libelle']) ?></span>
        </span>
      </label>
      <?php endforeach ?>
    </div>
  </div>

  <div class="grid sm:grid-cols-2 gap-4">
    <div>
      <label class="block text-sm font-semibold mb-1">Date de réception <span class="text-error">*</span></label>
      <input type="date" name="date_entree" required value="<?= $v('date_entree', date('Y-m-d')) ?>"
             class="w-full rounded-xl border-outline-variant">
    </div>
    <div>
      <label class="block text-sm font-semibold mb-1">Température (°C) <span class="text-error">*</span></label>
      <input type="text" inputmode="decimal" name="temperature" required placeholder="ex : 3,2"
             value="<?= $v('temperature') ?>" class="w-full rounded-xl border-outline-variant">
      <p class="text-xs text-on-surface-variant mt-1">Conformité transport : 4 °C ± 2 °C</p>
    </div>
  </div>

  <div class="grid sm:grid-cols-2 gap-4">
    <div>
      <label class="block text-sm font-semibold mb-1">Fournisseur <span class="text-error">*</span></label>
      <input type="text" name="fournisseur" required list="dl_fourn" value="<?= $v('fournisseur') ?>"
             class="w-full rounded-xl border-outline-variant">
      <datalist id="dl_fourn"><?php foreach ($fournisseurs as $f): ?><option value="<?= h($f) ?>"><?php endforeach ?></datalist>
    </div>
    <div>
      <label class="block text-sm font-semibold mb-1">Poids / quantité (kg) <span class="text-xs font-normal text-on-surface-variant">facultatif</span></label>
      <input type="text" inputmode="decimal" name="poids_kg" placeholder="ex : 128,5"
             value="<?= $v('poids_kg') ?>" class="w-full rounded-xl border-outline-variant">
    </div>
  </div>

  <details class="border-t border-outline-variant pt-4">
    <summary class="cursor-pointer text-sm font-semibold text-on-surface-variant">Champs complémentaires (facultatifs)</summary>
    <div class="grid sm:grid-cols-2 gap-4 mt-4">
      <div>
        <label class="block text-sm font-semibold mb-1">Forme</label>
        <select name="forme" class="w-full rounded-xl border-outline-variant">
          <option value="">—</option>
          <?php $sf = $_POST['forme'] ?? $e['forme'] ?? ''; foreach (LIB_FORME as $k => $l): ?>
          <option value="<?= $k ?>" <?= $sf === $k ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-semibold mb-1">État</label>
        <select name="etat" class="w-full rounded-xl border-outline-variant">
          <option value="">—</option>
          <?php $se = $_POST['etat'] ?? $e['etat'] ?? ''; foreach (LIB_ETAT as $k => $l): ?>
          <option value="<?= $k ?>" <?= $se === $k ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="sm:col-span-2">
        <label class="block text-sm font-semibold mb-1">N° de lot fournisseur / abattoir</label>
        <input type="text" name="num_lot_fournisseur" value="<?= $v('num_lot_fournisseur') ?>" class="w-full rounded-xl border-outline-variant">
      </div>
      <div class="sm:col-span-2">
        <label class="block text-sm font-semibold mb-1">N° d'identification de l'animal</label>
        <input type="text" name="num_animal" value="<?= $v('num_animal') ?>"
               placeholder="n° de boucle" class="w-full rounded-xl border-outline-variant">
      </div>
      <div class="sm:col-span-2">
        <label class="block text-sm font-semibold mb-1">Notes</label>
        <textarea name="notes" rows="2" class="w-full rounded-xl border-outline-variant"><?= $v('notes') ?></textarea>
      </div>
    </div>
  </details>

  <?php
  // ── Origine (règlement bovin). Élevage en propre : les valeurs par défaut
  //    couvrent la quasi-totalité des réceptions, on n'ouvre que pour déroger.
  $o_naiss = $_POST['pays_naissance']    ?? $e['pays_naissance']    ?? reglage('origine_naissance', 'France');
  $o_elev  = $_POST['pays_elevage']      ?? $e['pays_elevage']      ?? reglage('origine_elevage', 'France');
  $o_abat  = $_POST['pays_abattage']     ?? $e['pays_abattage']     ?? reglage('origine_abattage', 'France');
  $o_agr   = $_POST['agrement_abattoir'] ?? $e['agrement_abattoir'] ?? reglage('agrement_abattoir');
  $apercu  = origine_lot(['pays_naissance' => $o_naiss, 'pays_elevage' => $o_elev,
                          'pays_abattage' => $o_abat, 'agrement_abattoir' => $o_agr]);
  $derogation = $o_naiss !== reglage('origine_naissance', 'France')
             || $o_elev  !== reglage('origine_elevage', 'France')
             || $o_abat  !== reglage('origine_abattage', 'France');
  ?>
  <details class="border-t border-outline-variant pt-4" <?= $derogation ? 'open' : '' ?>>
    <summary class="cursor-pointer text-sm font-semibold">
      Origine
      <span class="font-normal text-on-surface-variant">
        — <?= $apercu['origine'] ? 'Origine : ' . h($apercu['origine']) : 'origines différentes' ?>
      </span>
    </summary>
    <p class="text-xs text-on-surface-variant mt-3 mb-3">
      Pré-rempli d'après vos réglages. Ne corrigez que pour un animal qui n'est pas né,
      élevé et abattu comme d'habitude — « Origine : France » n'est permise que si les
      trois pays sont identiques.
    </p>
    <div class="grid sm:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-semibold mb-1">Né en</label>
        <input type="text" name="pays_naissance" value="<?= h($o_naiss) ?>" class="w-full rounded-xl border-outline-variant">
      </div>
      <div>
        <label class="block text-sm font-semibold mb-1">Élevé en</label>
        <input type="text" name="pays_elevage" value="<?= h($o_elev) ?>" class="w-full rounded-xl border-outline-variant">
      </div>
      <div>
        <label class="block text-sm font-semibold mb-1">Abattu en</label>
        <input type="text" name="pays_abattage" value="<?= h($o_abat) ?>" class="w-full rounded-xl border-outline-variant">
      </div>
      <div>
        <label class="block text-sm font-semibold mb-1">N° d'agrément de l'abattoir</label>
        <input type="text" name="agrement_abattoir" value="<?= h($o_agr) ?>"
               placeholder="ex : FR 12.202.001 CE" class="w-full rounded-xl border-outline-variant">
      </div>
    </div>
    <?php if ($apercu['lignes']): ?>
    <div class="mt-4 bg-surface-container-low rounded-xl p-3 text-sm">
      <div class="text-xs text-on-surface-variant mb-1">Mentions qui seront portées sur l'étiquette</div>
      <?php foreach ($apercu['lignes'] as $l): ?><div class="font-semibold"><?= h($l) ?></div><?php endforeach ?>
      <div class="font-semibold"><?= h(mention_decoupe()) ?></div>
    </div>
    <?php endif ?>
  </details>

  <?php
  $photo_actuelle = $e['photo'] ?? null;
  $photo_aide = 'Bon de livraison, étiquette, document d\'abattoir…';
  require __DIR__ . '/includes/champ_photo.php';
  ?>

  <div class="bg-surface-container-low rounded-xl px-4 py-3 text-sm">
    <span class="text-on-surface-variant">N° de lot : </span>
    <strong class="lot-badge"><?= $e ? h($e['num_lot']) : 'généré automatiquement (ex. JB-' . date('dmy') . ')' ?></strong>
  </div>

  <button type="submit" name="save" value="1"
          class="barre-envoi w-full bg-primary text-on-primary rounded-full py-4 font-bold text-base active:scale-[.99] transition">
    <?= $e ? 'Enregistrer les modifications' : "Créer le lot d'entrée" ?>
  </button>
</form>

<?php
// ============================================================
//  DÉTAIL
// ============================================================
elseif ($action === 'voir' && $id):
    $e = entree_par_id($id);
    if (!$e) { echo '<p>Lot introuvable.</p>'; require __DIR__ . '/includes/footer.php'; exit; }
    $utilisations = sorties_de_entree($id);
    $solde = solde_entree($id);
?>
<div class="flex items-center gap-3 mb-6">
  <a href="entrees.php" class="material-symbols-outlined text-on-surface-variant hover:bg-surface-container p-2 rounded-full">arrow_back</a>
  <div class="flex-1">
    <div class="lot-badge text-2xl font-bold text-primary"><?= h($e['num_lot']) ?></div>
    <div class="text-sm text-on-surface-variant"><?= h($e['type_libelle']) ?> · <?= fmt_date($e['date_entree']) ?></div>
  </div>
  <a href="entrees.php?action=modifier&id=<?= $id ?>" class="material-symbols-outlined text-primary hover:bg-surface-container p-2 rounded-full">edit</a>
  <a href="etiquette.php?type=entree&id=<?= $id ?>" target="_blank" class="material-symbols-outlined text-primary hover:bg-surface-container p-2 rounded-full">print</a>
</div>

<div class="bg-surface rounded-xl border border-outline-variant p-5 mb-5">
  <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
    <?php
    $lignes = [
      'Fournisseur'     => h($e['fournisseur']),
      'Température'     => fmt_temp($e['temperature'] === null ? null : (float)$e['temperature']),
      'Poids'           => $e['poids_kg'] === null ? '—' : fmt_qte((float)$e['poids_kg']),
      'Forme'           => LIB_FORME[$e['forme']] ?? '—',
      'État'            => LIB_ETAT[$e['etat']] ?? '—',
      'Lot fournisseur' => h($e['num_lot_fournisseur']) ?: '—',
      'N° animal'       => h($e['num_animal'] ?? '') ?: '—',
      'Saisi par'       => h($e['cree_par']) ?: '—',
      'Saisi le'        => date('d/m/Y à H:i', strtotime($e['date_creation'])),
    ];
    foreach ($lignes as $k => $val): ?>
    <div><dt class="text-on-surface-variant text-xs uppercase tracking-wide"><?= $k ?></dt><dd class="font-semibold"><?= $val ?></dd></div>
    <?php endforeach ?>
  </dl>
  <?php $org = origine_lot($e); if ($org['complet']): ?>
  <div class="mt-4 bg-surface-container-low rounded-lg p-3 text-sm">
    <div class="text-xs text-on-surface-variant mb-1">Mentions d'origine</div>
    <?php foreach ($org['lignes'] as $l): ?><div class="font-semibold"><?= h($l) ?></div><?php endforeach ?>
    <div class="font-semibold"><?= h(mention_decoupe()) ?></div>
  </div>
  <?php endif ?>
  <?php if ($e['notes']): ?><p class="mt-4 text-sm bg-surface-container-low rounded-lg p-3"><?= nl2br(h($e['notes'])) ?></p><?php endif ?>
  <?php if ($e['photo']): ?>
  <a href="<?= UPLOAD_URL . h($e['photo']) ?>" target="_blank" class="inline-flex items-center gap-2 mt-4 text-primary text-sm font-semibold">
    <span class="material-symbols-outlined">photo_camera</span>Voir la photo d'archive</a>
  <?php endif ?>
</div>

<?php if ($solde): ?>
<div class="bg-surface rounded-xl border border-outline-variant p-5 mb-5">
  <h3 class="font-headline-md font-bold mb-3">Consommation</h3>
  <div class="flex justify-between text-sm mb-2">
    <span>Utilisé : <strong><?= fmt_qte($solde['utilise']) ?></strong></span>
    <span>Reste : <strong><?= fmt_qte($solde['reste']) ?></strong></span>
  </div>
  <div class="h-3 bg-surface-container rounded-full overflow-hidden">
    <div class="h-full bg-primary" style="width:<?= $solde['entree'] > 0 ? min(100, round($solde['utilise'] / $solde['entree'] * 100)) : 0 ?>%"></div>
  </div>
</div>
<?php endif ?>

<h3 class="font-headline-md font-bold mb-3">Fabrications issues de ce lot (<?= count($utilisations) ?>)</h3>
<?php if (!$utilisations): ?>
<p class="text-sm text-on-surface-variant bg-surface rounded-xl border border-outline-variant p-5">Aucune fabrication ne référence encore ce lot.</p>
<?php else: ?>
<div class="flex flex-col gap-2">
  <?php foreach ($utilisations as $s): ?>
  <a href="fabrications.php?action=voir&id=<?= (int)$s['id'] ?>" class="bg-surface rounded-xl border border-outline-variant p-4 flex items-center gap-3 hover:bg-surface-container-low">
    <div class="flex-1">
      <div class="lot-badge font-bold text-primary"><?= h($s['num_lot']) ?></div>
      <div class="text-sm"><?= h($s['produit']) ?> · <?= fmt_date($s['date_fabrication']) ?></div>
    </div>
    <div class="text-sm text-on-surface-variant"><?= $s['qte_utilisee'] === null ? '' : fmt_qte((float)$s['qte_utilisee']) ?></div>
    <span class="material-symbols-outlined text-on-surface-variant">chevron_right</span>
  </a>
  <?php endforeach ?>
</div>
<?php endif ?>

<?php if (!$utilisations): ?>
<a href="entrees.php?action=supprimer&id=<?= $id ?>" onclick="return confirm('Supprimer définitivement ce lot d\'entrée ?')"
   class="inline-block mt-6 text-sm text-error font-semibold">Supprimer ce lot</a>
<?php endif ?>

<?php
// ============================================================
//  LISTE
// ============================================================
else:
    $f_type = (int)($_GET['type'] ?? 0);
    $f_q    = trim($_GET['q'] ?? '');
    $where = []; $params = [];
    if ($f_type) { $where[] = 'e.type_id = ?'; $params[] = $f_type; }
    if ($f_q !== '') {
        $where[] = '(e.num_lot LIKE ? OR e.fournisseur LIKE ? OR e.num_lot_fournisseur LIKE ?)';
        array_push($params, "%$f_q%", "%$f_q%", "%$f_q%");
    }
    $sql = 'SELECT e.*, t.code, t.libelle AS type_libelle, t.couleur
            FROM lots_entree e JOIN types_matiere t ON t.id=e.type_id'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY e.date_entree DESC, e.id DESC LIMIT 300';
    $q = $pdo->prepare($sql); $q->execute($params); $lots = $q->fetchAll();
?>
<div class="flex items-center justify-between mb-5">
  <h2 class="font-headline-lg text-2xl font-bold text-primary">Entrées</h2>
  <a href="entrees.php?action=nouveau" class="bg-primary text-on-primary rounded-full px-5 py-3 font-bold text-sm flex items-center gap-2">
    <span class="material-symbols-outlined">add</span>Nouvelle
  </a>
</div>

<form method="get" class="flex gap-2 mb-5">
  <input type="text" name="q" value="<?= h($f_q) ?>" placeholder="N° de lot, fournisseur…"
         class="flex-1 rounded-full border-outline-variant text-sm">
  <select name="type" class="rounded-full border-outline-variant text-sm">
    <option value="0">Tous types</option>
    <?php foreach ($types as $t): ?>
    <option value="<?= (int)$t['id'] ?>" <?= $f_type == $t['id'] ? 'selected' : '' ?>><?= h($t['code']) ?> — <?= h($t['libelle']) ?></option>
    <?php endforeach ?>
  </select>
  <button class="bg-surface-container-high rounded-full px-4"><span class="material-symbols-outlined align-middle">search</span></button>
</form>

<?php if (!$lots): ?>
<div class="bg-surface rounded-xl border border-outline-variant p-8 text-center text-on-surface-variant">
  <span class="material-symbols-outlined text-4xl">inbox</span>
  <p class="mt-2 text-sm">Aucune entrée enregistrée.</p>
</div>
<?php else: ?>
<div class="flex flex-col gap-2">
  <?php foreach ($lots as $l): ?>
  <a href="entrees.php?action=voir&id=<?= (int)$l['id'] ?>" class="bg-surface rounded-xl border border-outline-variant p-4 flex items-center gap-3 hover:bg-surface-container-low">
    <span class="lot-badge text-xs font-bold text-white px-2 py-1 rounded" style="background:<?= h($l['couleur']) ?>"><?= h($l['code']) ?></span>
    <div class="flex-1 min-w-0">
      <div class="lot-badge font-bold"><?= h($l['num_lot']) ?></div>
      <div class="text-sm text-on-surface-variant truncate"><?= h($l['fournisseur']) ?> · <?= fmt_date($l['date_entree']) ?></div>
    </div>
    <div class="text-right text-sm">
      <div class="font-semibold"><?= $l['poids_kg'] === null ? '—' : fmt_qte((float)$l['poids_kg']) ?></div>
      <div class="text-xs text-on-surface-variant"><?= fmt_temp((float)$l['temperature']) ?></div>
    </div>
  </a>
  <?php endforeach ?>
</div>
<?php endif ?>

<?php endif ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

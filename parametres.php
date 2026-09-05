<?php
$page_active = 'parametres';
$page_title  = 'Paramètres';
require_once __DIR__ . '/includes/auth.php';
$moi = exiger_admin();

$pdo = db();
$msg = ''; $ok = '';

// ── Réglages généraux
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_reglages'])) {
    $prefixe = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', $_POST['prefixe_sortie'] ?? ''));
    $atelier = trim($_POST['nom_atelier'] ?? '');
    $st = $pdo->prepare('INSERT INTO reglages (cle, valeur) VALUES (?,?) ON DUPLICATE KEY UPDATE valeur=VALUES(valeur)');
    $st->execute(['prefixe_sortie', $prefixe]);
    $st->execute(['nom_atelier', $atelier]);
    foreach (['origine_naissance', 'origine_elevage', 'origine_abattage',
              'agrement_abattoir', 'pays_decoupe', 'agrement_atelier',
              'code_certificateur', 'origine_agricole'] as $cle) {
        if (isset($_POST[$cle])) $st->execute([$cle, trim($_POST[$cle])]);
    }
    header('Location: parametres.php?msg=reglages');
    exit;
}

// ── Ajout / modification d'un type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_type'])) {
    $tid     = (int)($_POST['type_id'] ?? 0);
    $code    = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['code'] ?? ''));
    $libelle = trim($_POST['libelle'] ?? '');
    $cat     = $_POST['categorie'] ?? 'autre';
    $couleur = preg_match('/^#[0-9A-Fa-f]{6}$/', $_POST['couleur'] ?? '') ? $_POST['couleur'] : '#2c5530';
    $actif   = isset($_POST['actif']) ? 1 : 0;

    if ($code === '' || $libelle === '') {
        $msg = 'Le code et le libellé sont obligatoires.';
    } elseif (!in_array($cat, ['viande', 'legume', 'sec', 'autre'], true)) {
        $msg = 'Catégorie invalide.';
    } else {
        try {
            if ($tid) {
                $pdo->prepare('UPDATE types_matiere SET code=?, libelle=?, categorie=?, couleur=?, actif=? WHERE id=?')
                    ->execute([$code, $libelle, $cat, $couleur, $actif, $tid]);
            } else {
                $ordre = (int)$pdo->query('SELECT COALESCE(MAX(ordre),0)+10 FROM types_matiere')->fetchColumn();
                $pdo->prepare('INSERT INTO types_matiere (code, libelle, categorie, couleur, actif, ordre) VALUES (?,?,?,?,?,?)')
                    ->execute([$code, $libelle, $cat, $couleur, $actif, $ordre]);
            }
            header('Location: parametres.php?msg=type');
            exit;
        } catch (PDOException $e) {
            $msg = strpos($e->getMessage(), 'uk_code') !== false
                 ? 'Ce code est déjà utilisé par un autre type.'
                 : 'Erreur : ' . $e->getMessage();
        }
    }
}

$flashes = ['reglages' => 'Réglages enregistrés.', 'type' => 'Type de matière enregistré.'];
$ok = $flashes[$_GET['msg'] ?? ''] ?? '';

$types  = $pdo->query('SELECT * FROM types_matiere ORDER BY ordre, libelle')->fetchAll();
$edit   = null;
if (($_GET['action'] ?? '') === 'type' && ($eid = (int)($_GET['id'] ?? 0))) {
    $q = $pdo->prepare('SELECT * FROM types_matiere WHERE id=?');
    $q->execute([$eid]);
    $edit = $q->fetch() ?: null;
}

$LIB_CAT = ['viande' => 'Viande', 'legume' => 'Légumes', 'sec' => 'Produits secs', 'autre' => 'Autre'];

require __DIR__ . '/includes/header.php';
?>

<h2 class="font-headline-lg text-2xl font-bold text-primary mb-6">Paramètres</h2>

<?php if ($msg): ?><div class="bg-error-container text-on-error-container rounded-xl px-4 py-3 mb-4 text-sm"><?= h($msg) ?></div><?php endif ?>
<?php if ($ok):  ?><div class="bg-primary-container text-on-primary-container rounded-xl px-4 py-3 mb-4 text-sm"><?= h($ok) ?></div><?php endif ?>

<!-- Réglages généraux -->
<section class="bg-surface rounded-xl border border-outline-variant p-5 mb-6">
  <h3 class="font-headline-md font-bold mb-4">Général</h3>
  <form method="post" class="flex flex-col gap-4">
    <div>
      <label class="block text-sm font-semibold mb-1">Nom de l'atelier</label>
      <input type="text" name="nom_atelier" value="<?= h(reglage('nom_atelier')) ?>" class="w-full rounded-xl border-outline-variant">
    </div>
    <div>
      <label class="block text-sm font-semibold mb-1">Préfixe des n° de lot de fabrication</label>
      <input type="text" name="prefixe_sortie" maxlength="4" value="<?= h(reglage('prefixe_sortie')) ?>"
             placeholder="vide = JJMMAA-1" class="w-full rounded-xl border-outline-variant">
      <p class="text-xs text-on-surface-variant mt-1">
        Sans préfixe : <strong class="lot-badge"><?= date('dmy') ?>-1</strong> ·
        avec « F » : <strong class="lot-badge">F<?= date('dmy') ?>-1</strong>
      </p>
    </div>
    <button name="save_reglages" value="1" class="self-start bg-primary text-on-primary rounded-full px-6 py-3 font-bold text-sm">Enregistrer</button>
  </form>
</section>

<!-- Types de matière -->
<section class="bg-surface rounded-xl border border-outline-variant p-5 mb-6">
  <h3 class="font-headline-md font-bold mb-1">Types de matière première</h3>
  <p class="text-xs text-on-surface-variant mb-4">Le code sert de préfixe au n° de lot d'entrée (ex. <strong class="lot-badge">JB-<?= date('dmy') ?></strong>).</p>

  <div class="flex flex-col gap-2 mb-5">
    <?php foreach ($types as $t): ?>
    <div class="flex items-center gap-3 py-2 border-b border-outline-variant last:border-0 <?= $t['actif'] ? '' : 'opacity-40' ?>">
      <span class="lot-badge text-xs font-bold text-white px-2 py-1 rounded" style="background:<?= h($t['couleur']) ?>"><?= h($t['code']) ?></span>
      <div class="flex-1 min-w-0">
        <div class="text-sm font-semibold truncate"><?= h($t['libelle']) ?></div>
        <div class="text-xs text-on-surface-variant"><?= $LIB_CAT[$t['categorie']] ?? '' ?><?= $t['actif'] ? '' : ' · désactivé' ?></div>
      </div>
      <a href="parametres.php?action=type&id=<?= (int)$t['id'] ?>" class="material-symbols-outlined text-primary p-2 rounded-full hover:bg-surface-container">edit</a>
    </div>
    <?php endforeach ?>
  </div>

  <form method="post" class="border-t border-outline-variant pt-4 grid sm:grid-cols-2 gap-4">
    <input type="hidden" name="type_id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <div class="sm:col-span-2 text-sm font-semibold"><?= $edit ? 'Modifier « ' . h($edit['libelle']) . ' »' : 'Ajouter un type' ?></div>
    <div>
      <label class="block text-sm font-semibold mb-1">Code</label>
      <input type="text" name="code" maxlength="5" required value="<?= h($edit['code'] ?? '') ?>"
             placeholder="ex : JB" class="w-full rounded-xl border-outline-variant uppercase">
    </div>
    <div>
      <label class="block text-sm font-semibold mb-1">Libellé</label>
      <input type="text" name="libelle" required value="<?= h($edit['libelle'] ?? '') ?>"
             placeholder="ex : Carcasse jeune bovin" class="w-full rounded-xl border-outline-variant">
    </div>
    <div>
      <label class="block text-sm font-semibold mb-1">Catégorie</label>
      <select name="categorie" class="w-full rounded-xl border-outline-variant">
        <?php foreach ($LIB_CAT as $k => $l): ?>
        <option value="<?= $k ?>" <?= ($edit['categorie'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach ?>
      </select>
      <p class="text-xs text-on-surface-variant mt-1">« Viande » déclenche le contrôle de température 4 °C ± 2 °C.</p>
    </div>
    <div>
      <label class="block text-sm font-semibold mb-1">Couleur</label>
      <input type="color" name="couleur" value="<?= h($edit['couleur'] ?? '#2c5530') ?>" class="w-full h-11 rounded-xl border-outline-variant">
    </div>
    <label class="sm:col-span-2 flex items-center gap-2 text-sm">
      <input type="checkbox" name="actif" value="1" <?= (!$edit || $edit['actif']) ? 'checked' : '' ?> class="rounded">
      Actif (proposé dans le formulaire d'entrée)
    </label>
    <div class="sm:col-span-2 flex gap-2">
      <button name="save_type" value="1" class="bg-primary text-on-primary rounded-full px-6 py-3 font-bold text-sm"><?= $edit ? 'Enregistrer' : 'Ajouter' ?></button>
      <?php if ($edit): ?><a href="parametres.php" class="bg-surface-container-high rounded-full px-6 py-3 font-bold text-sm">Annuler</a><?php endif ?>
    </div>
  </form>
</section>

<!-- Exports -->
<section class="bg-surface rounded-xl border border-outline-variant p-5">
  <h3 class="font-headline-md font-bold mb-1">Registres</h3>
  <p class="text-xs text-on-surface-variant mb-4">Export CSV équivalent aux classeurs papier, ouvrable dans Excel / LibreOffice.</p>
  <div class="flex flex-col sm:flex-row gap-3">
    <a href="export.php?type=registre_entrees" class="flex-1 bg-surface-container-low rounded-xl px-4 py-3 text-sm font-semibold flex items-center gap-2">
      <span class="material-symbols-outlined">download</span>Réception matières premières
    </a>
    <a href="export.php?type=registre_fabrications" class="flex-1 bg-surface-container-low rounded-xl px-4 py-3 text-sm font-semibold flex items-center gap-2">
      <span class="material-symbols-outlined">download</span>Suivi des fabrications
    </a>
  </div>
</section>

<section class="bg-surface rounded-xl border border-outline-variant p-5 mb-6">
  <h3 class="font-headline-md font-bold mb-1">Origine et agréments</h3>
  <p class="text-xs text-on-surface-variant mb-4">
    Valeurs pré-remplies à chaque réception. Avec un élevage en propre elles ne changent
    quasiment jamais : on ne les corrige que pour un animal atypique.
  </p>
  <form method="post" class="grid sm:grid-cols-2 gap-4">
    <div>
      <label class="block text-sm font-semibold mb-1">Né en</label>
      <input type="text" name="origine_naissance" value="<?= h(reglage('origine_naissance', 'France')) ?>" class="w-full rounded-xl border-outline-variant">
    </div>
    <div>
      <label class="block text-sm font-semibold mb-1">Élevé en</label>
      <input type="text" name="origine_elevage" value="<?= h(reglage('origine_elevage', 'France')) ?>" class="w-full rounded-xl border-outline-variant">
    </div>
    <div>
      <label class="block text-sm font-semibold mb-1">Abattu en</label>
      <input type="text" name="origine_abattage" value="<?= h(reglage('origine_abattage', 'France')) ?>" class="w-full rounded-xl border-outline-variant">
    </div>
    <div>
      <label class="block text-sm font-semibold mb-1">N° d'agrément de l'abattoir</label>
      <input type="text" name="agrement_abattoir" value="<?= h(reglage('agrement_abattoir')) ?>"
             placeholder="ex : FR 12.202.001 CE" class="w-full rounded-xl border-outline-variant">
    </div>
    <div>
      <label class="block text-sm font-semibold mb-1">Découpé en</label>
      <input type="text" name="pays_decoupe" value="<?= h(reglage('pays_decoupe', 'France')) ?>" class="w-full rounded-xl border-outline-variant">
    </div>
    <div>
      <label class="block text-sm font-semibold mb-1">N° d'agrément de votre atelier</label>
      <input type="text" name="agrement_atelier" value="<?= h(reglage('agrement_atelier')) ?>"
             placeholder="ex : FR 12.288.002 CE" class="w-full rounded-xl border-outline-variant">
    </div>
    <div class="sm:col-span-2 bg-surface-container-low rounded-xl p-3 text-sm">
      <div class="text-xs text-on-surface-variant mb-1">Aperçu des mentions portées sur l'étiquette</div>
      <?php
      $ap = origine_lot([
          'pays_naissance'    => reglage('origine_naissance', 'France'),
          'pays_elevage'      => reglage('origine_elevage', 'France'),
          'pays_abattage'     => reglage('origine_abattage', 'France'),
          'agrement_abattoir' => reglage('agrement_abattoir'),
      ]);
      foreach ($ap['lignes'] as $l): ?><div class="font-semibold"><?= h($l) ?></div><?php endforeach ?>
      <div class="font-semibold"><?= h(mention_decoupe()) ?></div>
    </div>
    <div>
      <label class="block text-sm font-semibold mb-1">Code de l'organisme certificateur</label>
      <input type="text" name="code_certificateur" value="<?= h(reglage('code_certificateur')) ?>"
             placeholder="ex : FR-BIO-01" class="w-full rounded-xl border-outline-variant">
    </div>
    <div>
      <label class="block text-sm font-semibold mb-1">Origine des ingrédients agricoles</label>
      <input type="text" name="origine_agricole" value="<?= h(reglage('origine_agricole', 'Agriculture France')) ?>"
             class="w-full rounded-xl border-outline-variant">
      <p class="text-xs text-on-surface-variant mt-1">Accompagne l'Eurofeuille : Agriculture France, UE, ou non UE.</p>
    </div>
    <div class="sm:col-span-2">
      <button name="save_reglages" value="1" class="bg-primary text-on-primary rounded-full px-6 py-3 font-bold text-sm">Enregistrer</button>
    </div>
  </form>
</section>

<section class="bg-surface rounded-xl border border-outline-variant p-5 mt-6">
  <h3 class="font-headline-md font-bold mb-1">Balance-étiqueteuse</h3>
  <p class="text-xs text-on-surface-variant mb-4">Fichiers à faire lire par DGI/RGI côté DFS.</p>
  <div class="flex flex-col sm:flex-row gap-3">
    <a href="export.php?type=dfs_articles" class="flex-1 bg-surface-container-low rounded-xl px-4 py-3 text-sm font-semibold flex items-center gap-2">
      <span class="material-symbols-outlined">download</span>Articles (PLU / EAN)
    </a>
    <a href="export.php?type=dfs_lots" class="flex-1 bg-surface-container-low rounded-xl px-4 py-3 text-sm font-semibold flex items-center gap-2">
      <span class="material-symbols-outlined">download</span>Lots fabriqués du jour
    </a>
  </div>
  <p class="text-xs text-on-surface-variant mt-4">
    <a class="text-primary underline font-semibold" href="maj.php">Mise à jour de la base</a>
    — à lancer après chaque livraison de nouvelle version.
  </p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

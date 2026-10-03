<?php
// ============================================================
//  Importer les étiquettes pesées à la balance — saisie différée.
//
//  Un écran par manipulation, cinq en tout. Le fichier reste en mémoire
//  entre les écrans : on peut corriger la lecture des colonnes sans
//  jamais redéposer quoi que ce soit.
//
//  Règle qui gouverne tout : le numéro de lot naît dans TraçaBoucher,
//  jamais à la balance. L'import ne crée pas les lots, il les complète.
//  Ce qui ne correspond à rien reste visible et se rattache à la main.
// ============================================================
$page_active = 'atelier';
$page_title  = 'Importer des étiquettes';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/atelier.php';
$moi = exiger_connexion();

$pdo = db();
$err = [];
$msg = $_SESSION['etiq_msg'] ?? ''; unset($_SESSION['etiq_msg']);

if (!etiquettes_installe()) {
    require __DIR__ . '/includes/header.php'; ?>
    <h2 class="font-headline-lg text-2xl font-bold text-primary mb-1">Importer des étiquettes</h2>
    <div class="bg-error-container text-on-error-container rounded-xl p-5 text-sm mt-4">
      Lancez d'abord la migration 007 depuis
      <a href="maj.php" class="underline font-semibold">Mise à jour de la base</a>.
    </div>
    <?php require __DIR__ . '/includes/footer.php'; exit;
}

const ETIQ_TAILLE_MAX = 5 * 1024 * 1024;

/** Le fichier déposé, ou une erreur en clair. */
function etiq_fichier_recu(array $f): array {
    $code = $f['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($code === UPLOAD_ERR_NO_FILE) return [null, 'Aucun fichier reçu.'];
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        return [null, 'Le fichier dépasse la taille autorisée par le serveur.'];
    }
    if ($code !== UPLOAD_ERR_OK) return [null, "L'envoi a échoué."];
    if (($f['size'] ?? 0) > ETIQ_TAILLE_MAX) {
        return [null, 'Fichier trop lourd (' . round($f['size'] / 1048576, 1) . ' Mo).'];
    }
    $contenu = @file_get_contents($f['tmp_name']);
    if ($contenu === false || $contenu === '') return [null, 'Fichier illisible.'];
    if (str_contains(substr($contenu, 0, 512), "\0")) {
        return [null, "Ce n'est pas un fichier texte. Depuis DFS, choisissez l'export au format CSV."];
    }
    return [$contenu, null];
}

// ── Réponse à la question sur l'agent (écran 1)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agent_reponse'])) {
    if ($_POST['agent_reponse'] === 'oui') {
        definir_reglage_utilisateur((int)$moi['id'], 'agent_installe', 'oui');
        header('Location: import_etiquettes.php'); exit;
    }
    header('Location: materiel.php'); exit;
}

// ── Écran 2 : lecture du fichier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['analyser'])) {
    [$contenu, $souci] = etiq_fichier_recu($_FILES['fichier'] ?? []);
    if ($souci) { $err[] = $souci; }
    else {
        $d = csv_decouper($contenu, ['lignes_entete' => isset($_POST['sans_entete']) ? 0 : 1]);
        if ($d['erreurs']) { $err = array_merge($err, $d['erreurs']); }
        // Le fichier reste en mémoire de session entre les écrans, pour
        // qu'on puisse corriger les colonnes sans le redéposer. Au-delà
        // de quelques dizaines de milliers de lignes, cette mémoire
        // devient déraisonnable — et ce n'est plus un relevé d'atelier.
        elseif (count($d['cellules']) > 20000) {
            $err[] = count($d['cellules']) . " lignes : c'est beaucoup pour un relevé d'étiquettes. "
                   . "Exportez une période plus courte depuis DFS.";
        }
        else {
            $_SESSION['etiq_lot'] = [
                'fichier'     => mb_substr((string)($_FILES['fichier']['name'] ?? 'etiquettes.csv'), 0, 255),
                'entete'      => $d['entete'],
                'cellules'    => $d['cellules'],
                'separateur'  => $d['separateur'],
                'colonnes'    => etiquettes_colonnes($d['entete'], $d['cellules']),
                'format_date' => 'd/m/Y',
            ];
            header('Location: import_etiquettes.php?etape=3'); exit;
        }
    }
}

// ── Écran 3 : correction des colonnes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remapper'])) {
    $depot = $_SESSION['etiq_lot'] ?? null;
    if (!$depot) { $err[] = 'Fichier expiré : redéposez-le.'; }
    else {
        $impose = [];
        foreach (array_keys(ETIQ_ROLES) as $role) { $impose[$role] = (string)($_POST['col_' . $role] ?? ''); }
        $depot['colonnes']    = etiquettes_colonnes($depot['entete'], $depot['cellules'], $impose);
        $depot['format_date'] = trim((string)($_POST['format_date'] ?? 'd/m/Y')) ?: 'd/m/Y';
        $_SESSION['etiq_lot'] = $depot;
        header('Location: import_etiquettes.php?etape=3'); exit;
    }
}

// ── Écran 4 : enregistrement et rattachement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enregistrer'])) {
    $depot = $_SESSION['etiq_lot'] ?? null;
    if (!$depot) { $err[] = 'Fichier expiré : redéposez-le.'; }
    else {
        $lu = etiquettes_interpreter($depot['entete'], $depot['cellules'], $depot['colonnes'], $depot['format_date']);
        if ($lu['erreurs']) { $err = array_merge($err, $lu['erreurs']); }
        elseif (!$lu['lignes']) { $err[] = 'Aucune étiquette lisible avec ces colonnes.'; }
        else {
            $pdo->prepare('INSERT INTO etiquettes_imports (source, fichier, lignes_lues, utilisateur_id)
                           VALUES (?,?,?,?)')
                ->execute(['fichier', $depot['fichier'], count($lu['lignes']), (int)$moi['id']]);
            $import_id = (int)$pdo->lastInsertId();

            $lots = lots_ouverts();
            $ins = $pdo->prepare('INSERT INTO etiquettes
                (import_id, sortie_id, num_lot_fichier, produit, plu, date_etiquette, poids_kg, empreinte)
                VALUES (?,?,?,?,?,?,?,?)');
            $connue = $pdo->prepare('SELECT COUNT(*) FROM etiquettes WHERE empreinte = ?');

            $importees = $doublons = $rattachees = 0;
            $occurrences = [];
            foreach ($lu['lignes'] as $l) {
                $cle = $l['num_lot'] . '|' . $l['produit'] . '|' . $l['date'] . '|' . $l['poids_kg'];
                $n = $occurrences[$cle] = ($occurrences[$cle] ?? -1) + 1;
                $emp = empreinte_etiquette($l['num_lot'], $l['produit'], $l['date'], $l['poids_kg'], $n);

                $connue->execute([$emp]);
                if ((int)$connue->fetchColumn() > 0) { $doublons++; continue; }

                $lot = lot_pour_etiquette($l, $lots);
                $ins->execute([$import_id, $lot ? (int)$lot['id'] : null, $l['num_lot'] ?: null,
                               $l['produit'] ?: null, $l['plu'], $l['date'], $l['poids_kg'], $emp]);
                $importees++;
                if ($lot) { $rattachees++; }
            }

            $pdo->prepare('UPDATE etiquettes_imports SET importees=?, doublons=?, rattachees=? WHERE id=?')
                ->execute([$importees, $doublons, $rattachees, $import_id]);
            unset($_SESSION['etiq_lot']);

            $_SESSION['etiq_msg'] = "$importees étiquette(s) enregistrée(s)"
                . ($rattachees ? ", $rattachees rattachée(s) à un lot" : '')
                . ($doublons ? ", $doublons déjà connue(s)" : '') . '.';
            header('Location: import_etiquettes.php?etape=4'); exit;
        }
    }
}

// ── Écran 4 : rattachement manuel d'une étiquette orpheline
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rattacher'])) {
    $sortie_id = (int)$_POST['sortie_id'];
    $ids = array_map('intval', (array)($_POST['etiquette_id'] ?? []));
    $ids = array_values(array_filter($ids));
    if ($sortie_id && $ids) {
        $marques = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE etiquettes SET sortie_id = ? WHERE id IN ($marques) AND sortie_id IS NULL")
            ->execute(array_merge([$sortie_id], $ids));
        $_SESSION['etiq_msg'] = count($ids) . ' étiquette(s) rattachée(s).';
    }
    header('Location: import_etiquettes.php?etape=4'); exit;
}

$depot = $_SESSION['etiq_lot'] ?? null;
$etape = (int)($_GET['etape'] ?? 0);
if ($etape === 3 && !$depot) { $etape = 0; }
if ($etape === 0 && !agent_question_reglee($moi)) { $etape = 1; }
if ($etape === 0) { $etape = 2; }

require __DIR__ . '/includes/header.php';
?>

<h2 class="font-headline-lg text-2xl font-bold text-primary mb-1">Importer des étiquettes</h2>
<p class="text-sm text-on-surface-variant mb-4">
  Les barquettes ont été pesées et étiquetées à la balance. On remonte le fichier.
</p>

<!-- Fil des étapes, toujours visible -->
<div class="flex items-center gap-1 mb-5 text-xs">
  <?php foreach ([1 => 'Agent', 2 => 'Fichier', 3 => 'Colonnes', 4 => 'Rattacher'] as $n => $lib): ?>
  <div class="flex-1 text-center">
    <div class="<?= $etape === $n ? 'bg-primary text-on-primary' : ($etape > $n ? 'bg-primary-container text-on-primary-container' : 'bg-surface-container-high text-on-surface-variant') ?>
                rounded-full w-7 h-7 flex items-center justify-center font-bold mx-auto mb-1">
      <?= $etape > $n ? '✓' : $n ?>
    </div>
    <span class="<?= $etape === $n ? 'font-bold text-primary' : 'text-on-surface-variant' ?>"><?= h($lib) ?></span>
  </div>
  <?php endforeach ?>
</div>

<?php if ($msg): ?><div class="bg-primary-container text-on-primary-container rounded-xl p-4 mb-4 text-sm"><?= h($msg) ?></div><?php endif ?>
<?php if ($err): ?>
<div class="bg-error-container text-on-error-container rounded-xl p-4 mb-4 text-sm">
  <ul class="list-disc pl-5"><?php foreach ($err as $e): ?><li><?= h($e) ?></li><?php endforeach ?></ul>
</div>
<?php endif ?>

<?php if ($etape === 1): ?>
<!-- ═══════════ ÉTAPE 1 — l'agent ═══════════ -->
<section class="bg-surface rounded-xl border border-outline-variant p-5">
  <h3 class="font-headline-md font-bold mb-2">L'agent est-il installé sur le PC de l'atelier ?</h3>
  <p class="text-sm text-on-surface-variant mb-4">
    C'est le petit programme qui fait le lien avec DFS. Avec lui, le fichier des étiquettes
    se récupère tout seul. Sans lui, il faut l'exporter à la main depuis DFS et le déposer ici —
    ce qui marche très bien aussi.
  </p>
  <form method="post" class="flex flex-col gap-2">
    <button name="agent_reponse" value="oui" class="bg-primary text-on-primary rounded-full py-4 font-bold">
      Oui, il est installé — ne plus me le demander
    </button>
    <button name="agent_reponse" value="non" class="bg-surface-container text-on-surface rounded-full py-4 font-bold">
      Non — m'aider à l'installer
    </button>
  </form>
  <p class="text-xs text-on-surface-variant mt-3 text-center">
    <a href="import_etiquettes.php?etape=2" class="underline">Plus tard, je dépose mon fichier maintenant</a>
  </p>
</section>

<?php elseif ($etape === 2): ?>
<!-- ═══════════ ÉTAPE 2 — le fichier ═══════════ -->
<section class="bg-surface rounded-xl border border-outline-variant p-5 mb-4">
  <h3 class="font-headline-md font-bold mb-2">Où trouver le fichier</h3>
  <ol class="text-sm flex flex-col gap-2 list-decimal pl-5 text-on-surface-variant">
    <li>Sur le PC de l'atelier, ouvrez <strong>DFS</strong>.</li>
    <li><strong>Programmation → Import/Export</strong>, choisissez l'export des pesées ou des étiquettes.</li>
    <li>Enregistrez-le au format <strong>CSV</strong>.</li>
    <li>Rapportez-le ici — clé USB, e-mail, partage réseau.</li>
  </ol>
  <p class="text-xs text-on-surface-variant mt-3">
    Peu importe la mise en forme : les colonnes sont reconnues toutes seules, et
    corrigeables à l'écran suivant si besoin.
  </p>
</section>

<form method="post" enctype="multipart/form-data" class="bg-surface rounded-xl border border-outline-variant p-5">
  <label class="block text-sm font-bold mb-2">Le fichier</label>
  <input type="file" name="fichier" accept=".csv,.txt,text/csv,text/plain" required
         class="w-full rounded-xl border border-outline-variant px-3 py-2 text-sm mb-3">
  <label class="flex items-center gap-3 text-sm mb-4">
    <input type="checkbox" name="sans_entete" class="rounded border-outline-variant text-primary">
    Ce fichier n'a pas de ligne d'en-tête
  </label>
  <button name="analyser" value="1" class="w-full bg-primary text-on-primary rounded-full py-4 font-bold">
    Lire le fichier
  </button>
  <p class="text-xs text-on-surface-variant mt-2 text-center">Rien n'est enregistré à cette étape.</p>
</form>

<?php elseif ($etape === 3): ?>
<!-- ═══════════ ÉTAPE 3 — les colonnes ═══════════ -->
<?php
  $lu = etiquettes_interpreter($depot['entete'], $depot['cellules'], $depot['colonnes'], $depot['format_date']);
  $entete = $depot['entete'];
  $choix = $entete ?: array_map('strval', range(0, max(0, count($depot['cellules'][0] ?? []) - 1)));
  $souci = $lu['erreurs'] || !$lu['lignes'];
?>
<section class="bg-surface rounded-xl border border-outline-variant p-5 mb-4">
  <h3 class="font-headline-md font-bold mb-1"><?= h($depot['fichier']) ?></h3>

  <?php if ($lu['erreurs']): ?>
  <div class="bg-error-container text-on-error-container rounded-lg p-3 text-sm my-3">
    <?php foreach ($lu['erreurs'] as $e): ?><p><?= h($e) ?></p><?php endforeach ?>
  </div>
  <?php elseif (!$lu['lignes']): ?>
  <div class="bg-error-container text-on-error-container rounded-lg p-3 text-sm my-3">
    Aucune étiquette lisible avec ces colonnes.
  </div>
  <?php else: ?>
  <div class="grid grid-cols-3 gap-3 my-3 text-center">
    <?php foreach ([['Étiquettes', count($lu['lignes'])],
                    ['Poids total', number_format(array_sum(array_map(fn($l) => (float)$l['poids_kg'], $lu['lignes'])), 2, ',', ' ') . ' kg'],
                    ['Ignorées', (int)$lu['ignorees']]] as [$lib, $v]): ?>
    <div class="bg-surface-container-low rounded-lg py-3">
      <div class="font-headline-md text-lg font-bold text-primary"><?= h((string)$v) ?></div>
      <div class="text-xs text-on-surface-variant"><?= h($lib) ?></div>
    </div>
    <?php endforeach ?>
  </div>
  <?php endif ?>

  <details class="border border-outline-variant rounded-lg" <?= $souci ? 'open' : '' ?>>
    <summary class="cursor-pointer select-none px-4 py-3 font-bold text-sm">
      Colonnes du fichier
      <span class="font-normal text-on-surface-variant">— à corriger si la lecture est fausse</span>
    </summary>
    <form method="post" class="px-4 pb-4">
      <div class="grid sm:grid-cols-2 gap-3">
        <?php foreach ([
            'num_lot' => 'Numéro de lot',
            'produit' => 'Produit',
            'date'    => 'Date',
            'poids'   => 'Poids',
            'plu'     => 'PLU',
        ] as $role => $lib): ?>
        <div>
          <label class="text-xs font-semibold block mb-1"><?= h($lib) ?></label>
          <select name="col_<?= h($role) ?>" class="w-full rounded-xl border-outline-variant">
            <option value="">— aucune —</option>
            <?php foreach ($choix as $i => $col): ?>
            <option value="<?= h((string)$col) ?>" <?= (string)($depot['colonnes'][$role] ?? '') === (string)$col ? 'selected' : '' ?>>
              <?= h($entete ? (string)$col : 'Colonne ' . ($i + 1)) ?>
              <?php if (isset($depot['cellules'][0][$i]) && trim((string)$depot['cellules'][0][$i]) !== ''): ?>
                — <?= h(mb_substr((string)$depot['cellules'][0][$i], 0, 24)) ?>
              <?php endif ?>
            </option>
            <?php endforeach ?>
          </select>
        </div>
        <?php endforeach ?>
        <div>
          <label class="text-xs font-semibold block mb-1">Format de date</label>
          <select name="format_date" class="w-full rounded-xl border-outline-variant">
            <?php foreach (['d/m/Y' => '31/12/2026', 'Y-m-d' => '2026-12-31', 'd-m-Y' => '31-12-2026',
                            'd.m.Y' => '31.12.2026', 'Ymd' => '20261231'] as $f => $ex): ?>
            <option value="<?= h($f) ?>" <?= $depot['format_date'] === $f ? 'selected' : '' ?>><?= h($ex) ?></option>
            <?php endforeach ?>
          </select>
        </div>
      </div>
      <button name="remapper" value="1" class="mt-3 bg-surface-container text-on-surface rounded-full px-5 py-2 font-bold text-sm">
        Appliquer
      </button>
    </form>
  </details>

  <?php if ($lu['lignes']): ?>
  <div class="overflow-x-auto border border-outline-variant rounded-lg mt-4">
    <table class="w-full text-sm">
      <thead class="bg-surface-container-low">
        <tr><th class="text-left px-3 py-2">Lot</th><th class="text-left px-3 py-2">Produit</th>
            <th class="text-left px-3 py-2">Date</th><th class="text-right px-3 py-2">Poids</th></tr>
      </thead>
      <tbody>
        <?php foreach (array_slice($lu['lignes'], 0, 15) as $l): ?>
        <tr class="border-t border-outline-variant">
          <td class="px-3 py-2 lot-badge text-xs"><?= h($l['num_lot'] ?: '—') ?></td>
          <td class="px-3 py-2"><?= h($l['produit'] ?: '—') ?></td>
          <td class="px-3 py-2 whitespace-nowrap"><?= $l['date'] ? h(date('d/m/Y', strtotime($l['date']))) : '—' ?></td>
          <td class="px-3 py-2 text-right whitespace-nowrap"><?= $l['poids_kg'] !== null ? fmt_qte((float)$l['poids_kg']) : '—' ?></td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <?php if (count($lu['lignes']) > 15): ?>
  <p class="text-xs text-on-surface-variant mt-2">… et <?= count($lu['lignes']) - 15 ?> autre(s).</p>
  <?php endif ?>
  <?php else: ?>
  <div class="overflow-x-auto border border-outline-variant rounded-lg mt-4">
    <table class="w-full text-xs">
      <?php if ($entete): ?>
      <thead class="bg-surface-container-low"><tr>
        <?php foreach ($entete as $c): ?><th class="text-left px-2 py-2 whitespace-nowrap"><?= h((string)$c) ?></th><?php endforeach ?>
      </tr></thead>
      <?php endif ?>
      <tbody>
        <?php foreach (array_slice($depot['cellules'], 0, 5) as $ligne): ?>
        <tr class="border-t border-outline-variant">
          <?php foreach ($ligne as $v): ?><td class="px-2 py-2 whitespace-nowrap"><?= h(mb_substr((string)$v, 0, 26)) ?></td><?php endforeach ?>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <p class="text-xs text-on-surface-variant mt-2">Les cinq premières lignes du fichier, telles qu'elles sont découpées.</p>
  <?php endif ?>
</section>

<form method="post" class="barre-envoi bg-surface border border-outline-variant rounded-xl p-3 flex justify-end gap-2">
  <a href="import_etiquettes.php?etape=2" class="bg-surface-container text-on-surface rounded-full px-5 py-2.5 font-bold text-sm">Autre fichier</a>
  <button name="enregistrer" value="1" <?= $lu['lignes'] ? '' : 'disabled' ?>
          class="bg-primary text-on-primary rounded-full px-5 py-2.5 font-bold text-sm <?= $lu['lignes'] ? '' : 'opacity-40' ?>">
    Enregistrer
  </button>
</form>

<?php else: ?>
<!-- ═══════════ ÉTAPE 4 — rattacher ═══════════ -->
<?php
  $orphelines = etiquettes_orphelines();
  $lots = lots_ouverts();
  // Regroupées par produit et par date : on rattache un paquet d'un coup,
  // pas une barquette à la fois.
  $groupes = [];
  foreach ($orphelines as $o) {
      $cle = ($o['num_lot_fichier'] ?: '—') . '|' . ($o['produit'] ?: '—') . '|' . ($o['date_etiquette'] ?: '');
      $groupes[$cle]['etiq'][] = $o;
      $groupes[$cle]['num']     = $o['num_lot_fichier'];
      $groupes[$cle]['produit'] = $o['produit'];
      $groupes[$cle]['date']    = $o['date_etiquette'];
  }
?>
<section class="bg-surface rounded-xl border border-outline-variant p-5 mb-4">
  <h3 class="font-headline-md font-bold mb-2">Lots complétés</h3>
  <?php
  $avec = array_values(array_filter($lots, fn($l) => $l['etiq']));
  if (!$avec): ?>
  <p class="text-sm text-on-surface-variant">Aucun lot ouvert n'a encore reçu d'étiquette.</p>
  <?php else: ?>
  <div class="flex flex-col gap-2">
    <?php foreach ($avec as $l): ?>
    <div class="flex items-center gap-3 border border-outline-variant rounded-lg px-4 py-3">
      <span class="material-symbols-outlined text-primary">check_circle</span>
      <span class="flex-1 min-w-0">
        <span class="lot-badge font-bold"><?= h($l['num_lot']) ?></span>
        <span class="block text-xs text-on-surface-variant truncate"><?= h($l['produit']) ?></span>
      </span>
      <span class="text-sm text-right whitespace-nowrap">
        <?= count($l['etiq']) ?> étiq.<br>
        <strong><?= fmt_qte($l['poids_etiq']) ?></strong>
      </span>
    </div>
    <?php endforeach ?>
  </div>
  <p class="text-xs text-on-surface-variant mt-3">
    Le poids pesé sera proposé comme quantité produite à la clôture, dans
    <a href="atelier.php" class="text-primary underline font-semibold">Atelier</a>.
  </p>
  <?php endif ?>
</section>

<?php if ($groupes): ?>
<section class="bg-surface rounded-xl border border-secondary p-5 mb-4">
  <h3 class="font-headline-md font-bold mb-1 flex items-center gap-2">
    <span class="material-symbols-outlined text-secondary">warning</span>
    <?= count($orphelines) ?> étiquette(s) sans lot
  </h3>
  <p class="text-xs text-on-surface-variant mb-4">
    Leur numéro ne correspond à aucun lot ouvert — ou deux lots du même produit étaient
    ouverts le même jour, et c'est à vous de dire lequel.
  </p>
  <div class="flex flex-col gap-3">
    <?php foreach ($groupes as $g): ?>
    <form method="post" class="border border-outline-variant rounded-lg p-4">
      <?php foreach ($g['etiq'] as $e): ?>
      <input type="hidden" name="etiquette_id[]" value="<?= (int)$e['id'] ?>">
      <?php endforeach ?>
      <div class="mb-3">
        <span class="lot-badge text-sm font-bold"><?= h($g['num'] ?: 'sans numéro') ?></span>
        <span class="block text-sm font-semibold"><?= h($g['produit'] ?: '(produit inconnu)') ?></span>
        <span class="block text-xs text-on-surface-variant">
          <?= $g['date'] ? h(date('d/m/Y', strtotime($g['date']))) : 'sans date' ?>
          · <?= count($g['etiq']) ?> étiquette(s)
          · <?= fmt_qte(array_sum(array_map(fn($e) => (float)$e['poids_kg'], $g['etiq']))) ?>
        </span>
      </div>
      <?php if ($lots): ?>
      <div class="flex gap-2">
        <select name="sortie_id" required class="flex-1 rounded-xl border-outline-variant">
          <option value="">— rattacher au lot… —</option>
          <?php foreach ($lots as $l): ?>
          <option value="<?= (int)$l['id'] ?>"><?= h($l['num_lot']) ?> — <?= h($l['produit']) ?></option>
          <?php endforeach ?>
        </select>
        <button name="rattacher" value="1" class="bg-primary text-on-primary rounded-full px-5 font-bold text-sm shrink-0">
          Rattacher
        </button>
      </div>
      <?php else: ?>
      <p class="text-sm text-error">
        Aucun lot ouvert. <a href="atelier.php" class="underline font-semibold">Ouvrez le lot correspondant</a>,
        puis revenez ici.
      </p>
      <?php endif ?>
    </form>
    <?php endforeach ?>
  </div>
</section>
<?php endif ?>

<div class="flex flex-wrap gap-2">
  <a href="atelier.php" class="bg-primary text-on-primary rounded-full px-6 py-3 font-bold text-sm">Aller clôturer les lots</a>
  <a href="import_etiquettes.php?etape=2" class="bg-surface-container text-on-surface rounded-full px-6 py-3 font-bold text-sm">Importer un autre fichier</a>
</div>
<?php endif ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

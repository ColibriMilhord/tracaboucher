<?php
// ============================================================
//  Paramètres — rangés par onglets.
//
//  Tout était empilé sur une seule page : le nom de l'atelier, les
//  mentions d'origine, les types de matière, les comptes et le jeton de
//  la balance. Un artisan qui cherche son numéro d'agrément n'a pas à
//  traverser des réglages de connexion pour le trouver.
//
//  Les onglets sont nommés par ce qu'on vient y faire, pas par le
//  domaine technique auquel ils appartiennent.
// ============================================================
$page_active = 'parametres';
$page_title  = 'Paramètres';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/atelier.php';
$moi = exiger_admin();

$pdo = db();
$msg = '';

const ONGLETS_PARAM = [
    'atelier'   => ['Mon atelier',       'storefront'],
    'etiquettes'=> ['Étiquettes',        'label'],
    'matieres'  => ['Matières premières','inventory'],
    'comptes'   => ['Comptes',           'group'],
    'systeme'   => ['Système',           'settings'],
];

$onglet = $_GET['onglet'] ?? 'atelier';
if (!isset(ONGLETS_PARAM[$onglet])) { $onglet = 'atelier'; }

/** Retour sur l'onglet d'où l'on vient, avec son message. */
function retour(string $onglet, string $msg = ''): void {
    header('Location: parametres.php?onglet=' . urlencode($onglet) . ($msg ? '&msg=' . urlencode($msg) : ''));
    exit;
}

// ── Réglages : on n'enregistre QUE ce que le formulaire a envoyé.
//    Les onglets ne portent chacun qu'une partie des réglages ; écrire
//    les absents les viderait au premier enregistrement.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_reglages'])) {
    $st = $pdo->prepare('INSERT INTO reglages (cle, valeur) VALUES (?,?) ON DUPLICATE KEY UPDATE valeur=VALUES(valeur)');

    if (isset($_POST['prefixe_sortie'])) {
        $st->execute(['prefixe_sortie', strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', $_POST['prefixe_sortie']))]);
    }
    foreach (['nom_atelier', 'origine_naissance', 'origine_elevage', 'origine_abattage',
              'agrement_abattoir', 'pays_decoupe', 'agrement_atelier',
              'code_certificateur', 'origine_agricole'] as $cle) {
        if (isset($_POST[$cle])) { $st->execute([$cle, trim($_POST[$cle])]); }
    }
    retour((string)($_POST['onglet'] ?? 'atelier'), 'reglages');
}

// ── Ajout / modification d'un type de matière première
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_type'])) {
    $tid     = (int)($_POST['type_id'] ?? 0);
    $code    = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['code'] ?? ''));
    $libelle = trim($_POST['libelle'] ?? '');
    $cat     = $_POST['categorie'] ?? 'autre';
    $couleur = preg_match('/^#[0-9A-Fa-f]{6}$/', $_POST['couleur'] ?? '') ? $_POST['couleur'] : '#2c5530';
    $actif   = isset($_POST['actif']) ? 1 : 0;
    $onglet  = 'matieres';

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
            retour('matieres', 'type');
        } catch (PDOException $e) {
            $msg = strpos($e->getMessage(), 'uk_code') !== false
                 ? 'Ce code est déjà utilisé par un autre type.'
                 : 'Erreur : ' . $e->getMessage();
        }
    }
}

$flashes = ['reglages' => 'Réglages enregistrés.', 'type' => 'Type de matière enregistré.'];
$ok = $flashes[$_GET['msg'] ?? ''] ?? '';

$types = $pdo->query('SELECT * FROM types_matiere ORDER BY ordre, libelle')->fetchAll();
$edit  = null;
if (($_GET['action'] ?? '') === 'type' && ($eid = (int)($_GET['id'] ?? 0))) {
    $q = $pdo->prepare('SELECT * FROM types_matiere WHERE id=?');
    $q->execute([$eid]);
    $edit = $q->fetch() ?: null;
    if ($edit) { $onglet = 'matieres'; }
}

$LIB_CAT = ['viande' => 'Viande', 'legume' => 'Légumes', 'sec' => 'Produits secs', 'autre' => 'Autre'];

// Ce qui manque encore, par onglet : une pastille vaut mieux qu'un
// réglage oublié qu'on découvre sur une étiquette non conforme.
$alertes = [
    'atelier'    => reglage('nom_atelier') === '' ? 1 : 0,
    'etiquettes' => (reglage('agrement_atelier') === '' ? 1 : 0) + (reglage('agrement_abattoir') === '' ? 1 : 0),
    'matieres'   => count(array_filter($types, fn($t) => $t['actif'])) === 0 ? 1 : 0,
    'comptes'    => diagnostic_comptes() === null ? 0 : 1,
    'systeme'    => 0,
];

require __DIR__ . '/includes/header.php';
?>

<h2 class="font-headline-lg text-2xl font-bold text-primary mb-1">Paramètres</h2>
<p class="text-sm text-on-surface-variant mb-4">Réglé une fois, puis oublié. Seules les matières premières bougent de temps en temps.</p>

<!-- Onglets : défilables sur téléphone, sans JavaScript -->
<nav class="flex gap-2 overflow-x-auto pb-2 mb-5 -mx-4 px-4">
  <?php foreach (ONGLETS_PARAM as $cle => [$lib, $icone]): $actif = $onglet === $cle; ?>
  <a href="parametres.php?onglet=<?= h($cle) ?>"
     class="<?= $actif ? 'bg-primary text-on-primary' : 'bg-surface text-on-surface-variant border border-outline-variant' ?>
            rounded-full px-4 py-2.5 text-sm font-bold whitespace-nowrap flex items-center gap-2 shrink-0">
    <span class="material-symbols-outlined text-base"><?= $icone ?></span><?= h($lib) ?>
    <?php if ($alertes[$cle]): ?>
    <span class="<?= $actif ? 'bg-on-primary text-primary' : 'bg-error text-on-error' ?>
                 rounded-full w-5 h-5 flex items-center justify-center text-xs font-extrabold"><?= (int)$alertes[$cle] ?></span>
    <?php endif ?>
  </a>
  <?php endforeach ?>
</nav>

<?php if ($msg): ?><div class="bg-error-container text-on-error-container rounded-xl px-4 py-3 mb-4 text-sm"><?= h($msg) ?></div><?php endif ?>
<?php if ($ok):  ?><div class="bg-primary-container text-on-primary-container rounded-xl px-4 py-3 mb-4 text-sm"><?= h($ok) ?></div><?php endif ?>


<?php if ($onglet === 'atelier'): ?>
<!-- ═══════════ MON ATELIER ═══════════ -->
<form method="post" class="bg-surface rounded-xl border border-outline-variant p-5 flex flex-col gap-5">
  <input type="hidden" name="onglet" value="atelier">
  <div>
    <label class="block text-sm font-semibold mb-1">Nom de l'atelier</label>
    <input type="text" name="nom_atelier" value="<?= h(reglage('nom_atelier')) ?>"
           placeholder="ex : Boucherie du Causse" class="w-full rounded-xl border-outline-variant">
    <p class="text-xs text-on-surface-variant mt-1">Apparaît en tête des registres remis en cas de contrôle.</p>
  </div>

  <div>
    <label class="block text-sm font-semibold mb-1">Préfixe des numéros de lot</label>
    <input type="text" name="prefixe_sortie" maxlength="4" value="<?= h(reglage('prefixe_sortie')) ?>"
           placeholder="laissez vide" class="w-full rounded-xl border-outline-variant">
    <div class="bg-surface-container-low rounded-xl p-3 mt-2 text-sm">
      <div class="text-xs text-on-surface-variant mb-1">Vos numéros de lot ressembleront à</div>
      <div class="lot-badge font-bold text-base"><?= h(reglage('prefixe_sortie')) . date('dmy') ?>-1</div>
      <div class="text-xs text-on-surface-variant mt-1">
        Le chiffre final est l'occurrence : le deuxième lot du jour sera
        <strong class="lot-badge"><?= h(reglage('prefixe_sortie')) . date('dmy') ?>-2</strong>.
      </div>
    </div>
  </div>

  <button name="save_reglages" value="1" class="self-start bg-primary text-on-primary rounded-full px-6 py-3 font-bold text-sm">
    Enregistrer
  </button>
</form>


<?php elseif ($onglet === 'etiquettes'): ?>
<!-- ═══════════ ÉTIQUETTES ═══════════ -->
<p class="text-sm text-on-surface-variant mb-4">
  Ce qui s'imprime sur chaque étiquette. Avec un élevage en propre, ces valeurs ne changent
  quasiment jamais : on ne les corrige que pour un animal atypique.
</p>

<form method="post" class="flex flex-col gap-4">
  <input type="hidden" name="onglet" value="etiquettes">

  <section class="bg-surface rounded-xl border border-outline-variant p-5">
    <h3 class="font-headline-md font-bold mb-4">D'où vient la viande</h3>
    <div class="grid sm:grid-cols-2 gap-4">
      <?php foreach ([
          'origine_naissance' => ['Né en', 'France'],
          'origine_elevage'   => ['Élevé en', 'France'],
          'origine_abattage'  => ['Abattu en', 'France'],
          'pays_decoupe'      => ['Découpé en', 'France'],
      ] as $cle => [$lib, $defaut]): ?>
      <div>
        <label class="block text-sm font-semibold mb-1"><?= h($lib) ?></label>
        <input type="text" name="<?= h($cle) ?>" value="<?= h(reglage($cle, $defaut)) ?>" class="w-full rounded-xl border-outline-variant">
      </div>
      <?php endforeach ?>
    </div>
  </section>

  <section class="bg-surface rounded-xl border border-outline-variant p-5">
    <h3 class="font-headline-md font-bold mb-1">Numéros d'agrément</h3>
    <p class="text-xs text-on-surface-variant mb-4">Obligatoires sur les étiquettes de viande bovine.</p>
    <div class="grid sm:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-semibold mb-1">Abattoir</label>
        <input type="text" name="agrement_abattoir" value="<?= h(reglage('agrement_abattoir')) ?>"
               placeholder="ex : FR 12.202.001 CE"
               class="w-full rounded-xl <?= reglage('agrement_abattoir') === '' ? 'border-error' : 'border-outline-variant' ?>">
      </div>
      <div>
        <label class="block text-sm font-semibold mb-1">Votre atelier de découpe</label>
        <input type="text" name="agrement_atelier" value="<?= h(reglage('agrement_atelier')) ?>"
               placeholder="ex : FR 12.288.002 CE"
               class="w-full rounded-xl <?= reglage('agrement_atelier') === '' ? 'border-error' : 'border-outline-variant' ?>">
      </div>
    </div>
  </section>

  <section class="bg-surface rounded-xl border border-outline-variant p-5">
    <h3 class="font-headline-md font-bold mb-1">Mention biologique</h3>
    <p class="text-xs text-on-surface-variant mb-4">
      Utilisée quand un produit atteint 95 % d'ingrédients bio — c'est ce qui autorise l'Eurofeuille.
    </p>
    <div class="grid sm:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-semibold mb-1">Organisme certificateur</label>
        <input type="text" name="code_certificateur" value="<?= h(reglage('code_certificateur')) ?>"
               placeholder="ex : FR-BIO-01" class="w-full rounded-xl border-outline-variant">
      </div>
      <div>
        <label class="block text-sm font-semibold mb-1">Origine des ingrédients</label>
        <input type="text" name="origine_agricole" value="<?= h(reglage('origine_agricole', 'Agriculture France')) ?>"
               class="w-full rounded-xl border-outline-variant">
        <p class="text-xs text-on-surface-variant mt-1">Agriculture France, UE, ou non UE.</p>
      </div>
    </div>
  </section>

  <section class="bg-primary-container text-on-primary-container rounded-xl p-5">
    <h3 class="font-headline-md font-bold mb-2 flex items-center gap-2">
      <span class="material-symbols-outlined">label</span>Ce que lira le client
    </h3>
    <?php
    $ap = origine_lot([
        'pays_naissance'    => reglage('origine_naissance', 'France'),
        'pays_elevage'      => reglage('origine_elevage', 'France'),
        'pays_abattage'     => reglage('origine_abattage', 'France'),
        'agrement_abattoir' => reglage('agrement_abattoir'),
    ]);
    ?>
    <div class="bg-surface text-on-surface rounded-lg p-4 text-sm">
      <?php if (!$ap['complet']): ?>
      <p class="text-error font-semibold">Mentions incomplètes : renseignez les pays d'origine ci-dessus.</p>
      <?php else: ?>
      <?php foreach ($ap['lignes'] as $l): ?><div class="font-semibold"><?= h($l) ?></div><?php endforeach ?>
      <div class="font-semibold"><?= h(mention_decoupe()) ?></div>
      <?php endif ?>
    </div>
  </section>

  <div class="barre-envoi bg-surface border border-outline-variant rounded-xl p-3">
    <button name="save_reglages" value="1" class="w-full bg-primary text-on-primary rounded-full py-3 font-bold text-sm">
      Enregistrer
    </button>
  </div>
</form>


<?php elseif ($onglet === 'matieres'): ?>
<!-- ═══════════ MATIÈRES PREMIÈRES ═══════════ -->
<p class="text-sm text-on-surface-variant mb-4">
  Ce que vous recevez : carcasses, légumes, ingrédients secs. Le code sert de préfixe au
  numéro de lot d'entrée — <strong class="lot-badge">JB-<?= date('dmy') ?></strong> pour un jeune bovin.
</p>

<section class="bg-surface rounded-xl border border-outline-variant p-5 mb-4">
  <h3 class="font-headline-md font-bold mb-3"><?= count($types) ?> type(s) déclaré(s)</h3>
  <?php if (!$types): ?>
  <p class="text-sm text-error">Aucun type : vous ne pourrez pas saisir de réception. Ajoutez-en un ci-dessous.</p>
  <?php else: ?>
  <div class="flex flex-col gap-2">
    <?php foreach ($types as $t): ?>
    <div class="flex items-center gap-3 py-2 border-b border-outline-variant last:border-0 <?= $t['actif'] ? '' : 'opacity-40' ?>">
      <span class="lot-badge text-xs font-bold text-white px-2 py-1 rounded" style="background:<?= h($t['couleur']) ?>"><?= h($t['code']) ?></span>
      <div class="flex-1 min-w-0">
        <div class="text-sm font-semibold truncate"><?= h($t['libelle']) ?></div>
        <div class="text-xs text-on-surface-variant"><?= $LIB_CAT[$t['categorie']] ?? '' ?><?= $t['actif'] ? '' : ' · désactivé' ?></div>
      </div>
      <a href="parametres.php?onglet=matieres&action=type&id=<?= (int)$t['id'] ?>"
         class="material-symbols-outlined text-primary p-2 rounded-full hover:bg-surface-container">edit</a>
    </div>
    <?php endforeach ?>
  </div>
  <?php endif ?>
</section>

<form method="post" class="bg-surface rounded-xl border <?= $edit ? 'border-primary' : 'border-outline-variant' ?> p-5 grid sm:grid-cols-2 gap-4">
  <input type="hidden" name="type_id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <h3 class="sm:col-span-2 font-headline-md font-bold">
    <?= $edit ? 'Modifier « ' . h($edit['libelle']) . ' »' : 'Ajouter un type' ?>
  </h3>
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
    <p class="text-xs text-on-surface-variant mt-1">Pour repérer le type d'un coup d'œil dans les listes.</p>
  </div>
  <label class="sm:col-span-2 flex items-center gap-2 text-sm">
    <input type="checkbox" name="actif" value="1" <?= (!$edit || $edit['actif']) ? 'checked' : '' ?> class="rounded">
    Proposé dans le formulaire de réception
  </label>
  <div class="sm:col-span-2 flex gap-2">
    <button name="save_type" value="1" class="bg-primary text-on-primary rounded-full px-6 py-3 font-bold text-sm"><?= $edit ? 'Enregistrer' : 'Ajouter' ?></button>
    <?php if ($edit): ?><a href="parametres.php?onglet=matieres" class="bg-surface-container-high rounded-full px-6 py-3 font-bold text-sm">Annuler</a><?php endif ?>
  </div>
</form>


<?php elseif ($onglet === 'comptes'): ?>
<!-- ═══════════ COMPTES ═══════════ -->
<?php $souci = diagnostic_comptes(); ?>
<section class="rounded-xl p-5 mb-4 <?= $souci === null ? 'bg-primary-container text-on-primary-container' : 'bg-error-container text-on-error-container' ?>">
  <div class="font-bold flex items-center gap-2 mb-1">
    <span class="material-symbols-outlined"><?= $souci === null ? 'check_circle' : 'warning' ?></span>
    <?= $souci === null ? 'Connexion unique active' : 'Connexion unique inactive' ?>
  </div>
  <p class="text-sm">
    <?= $souci === null
        ? 'Les comptes sont ceux du portail CAUSSELOT : un seul identifiant pour tous les services.'
        : h($souci) ?>
  </p>
</section>

<section class="bg-surface rounded-xl border border-outline-variant p-5 mb-4">
  <h3 class="font-headline-md font-bold mb-1">Qui peut se connecter</h3>
  <p class="text-sm text-on-surface-variant mb-4">
    Les comptes se créent et se modifient sur le portail CAUSSELOT, pas ici : ils servent à
    tous les services, et les gérer à deux endroits les ferait diverger.
  </p>
  <?php if (defined('CAUSSELOT_URL') && CAUSSELOT_URL !== ''): ?>
  <a href="<?= h(rtrim(CAUSSELOT_URL, '/')) ?>/gestion_utilisateurs.php"
     class="bg-primary text-on-primary rounded-full px-6 py-3 font-bold text-sm inline-flex items-center gap-2">
    <span class="material-symbols-outlined">open_in_new</span>Gérer les comptes
  </a>
  <?php else: ?>
  <p class="text-sm text-error">
    L'adresse du portail n'est pas renseignée (<code>CAUSSELOT_URL</code> dans <code>config.local.php</code>).
  </p>
  <?php endif ?>
</section>

<section class="bg-surface rounded-xl border border-outline-variant p-5">
  <h3 class="font-headline-md font-bold mb-1">Reprise des anciens comptes</h3>
  <p class="text-sm text-on-surface-variant mb-4">
    À faire une seule fois : les comptes qui n'existaient que dans TraçaBoucher doivent être
    recopiés dans le portail, sans quoi leurs titulaires ne peuvent plus se connecter.
    Les mots de passe sont conservés.
  </p>
  <a href="migrer_comptes.php" class="bg-surface-container text-on-surface rounded-full px-6 py-3 font-bold text-sm inline-block">
    Reprendre les comptes
  </a>
</section>


<?php else: ?>
<!-- ═══════════ SYSTÈME ═══════════ -->
<p class="text-sm text-on-surface-variant mb-4">
  Ce qu'on ne touche qu'après une mise à jour, ou quand quelque chose coince.
</p>

<div class="flex flex-col gap-3">
  <?php
  $liens = [
    ['maj.php', 'database', 'Mise à jour de la base',
     'À lancer après chaque nouvelle version livrée.'],
    ['materiel.php', 'devices', 'Matériel',
     "Le PC de l'atelier, l'agent de la balance et son jeton."],
    ['exports.php', 'download', 'Exports',
     'Registres réglementaires et fichiers pour la balance.'],
    ['produits.php', 'inventory', 'Produits',
     'Le catalogue envoyé à la balance : PLU, EAN, prix, DLC.'],
  ];
  if (defined('DIAGNOSTIC_CLE') && DIAGNOSTIC_CLE !== '') {
    $liens[] = ['diagnostic.php?cle=' . urlencode(DIAGNOSTIC_CLE), 'troubleshoot', 'Diagnostic des bases',
                'Quelles bases le serveur voit réellement. À refermer ensuite.'];
  }
  foreach ($liens as [$url, $icone, $titre, $aide]): ?>
  <a href="<?= h($url) ?>" class="bg-surface rounded-xl border border-outline-variant p-4 flex items-center gap-4 hover:bg-surface-container-low">
    <span class="material-symbols-outlined text-2xl text-primary shrink-0"><?= $icone ?></span>
    <span class="flex-1 min-w-0">
      <span class="block font-bold text-sm"><?= h($titre) ?></span>
      <span class="block text-xs text-on-surface-variant"><?= h($aide) ?></span>
    </span>
    <span class="material-symbols-outlined text-on-surface-variant shrink-0">chevron_right</span>
  </a>
  <?php endforeach ?>
</div>

<div class="bg-surface-container-low rounded-xl p-4 mt-5 text-sm">
  <div class="text-xs text-on-surface-variant mb-1">Version déployée sur ce serveur</div>
  <strong><?= h(version_affichee()) ?></strong>
  <p class="text-xs text-on-surface-variant mt-2">
    Si ce numéro ne change pas après une livraison, c'est le déploiement qui n'est pas passé.
  </p>
</div>
<?php endif ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

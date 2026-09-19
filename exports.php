<?php
// ============================================================
//  Exports CSV — registres réglementaires et fichiers balance.
//
//  Ces exports vivaient au milieu de la page Paramètres, réservée aux
//  administrateurs. export.php, lui, n'a jamais demandé plus qu'une
//  session : c'était donc la porte d'entrée qui était fermée, pas
//  l'export. Un atelier qui doit sortir son registre de réception n'a
//  pas à être administrateur pour ça.
// ============================================================
$page_active = 'exports';
$page_title  = 'Exports';
require_once __DIR__ . '/includes/auth.php';
$moi = exiger_connexion();

$pdo = db();

// Volumétrie : savoir ce qu'on s'apprête à télécharger évite d'ouvrir un
// fichier vide en se demandant si l'export a échoué.
function compte_table(string $table): ?int {
    try { return (int)db()->query("SELECT COUNT(*) FROM $table")->fetchColumn(); }
    catch (PDOException $e) { return null; }
}

$nb_entrees      = compte_table('lots_entree');
$nb_fabrications = compte_table('lots_sortie');
$nb_produits     = referentiel_produits_pret()
    ? (int)$pdo->query('SELECT COUNT(*) FROM produits WHERE actif=1')->fetchColumn() : null;
$nb_lots_jour = null;
try {
    $q = $pdo->prepare('SELECT COUNT(*) FROM lots_sortie WHERE date_fabrication = ?');
    $q->execute([date('Y-m-d')]);
    $nb_lots_jour = (int)$q->fetchColumn();
} catch (PDOException $e) { /* table absente : la migration n'est pas passée */ }

$sections = [
    [
        'titre' => 'Registres réglementaires',
        'aide'  => "L'équivalent des classeurs papier, en CSV ouvrable dans Excel ou LibreOffice. "
                 . "C'est ce qui se présente en cas de contrôle.",
        'liens' => [
            ['registre_entrees', 'Réception des matières premières', 'inventory',
             $nb_entrees === null ? null : $nb_entrees . ' lot(s) d\'entrée'],
            ['registre_fabrications', 'Suivi des fabrications', 'factory',
             $nb_fabrications === null ? null : $nb_fabrications . ' fabrication(s)'],
        ],
    ],
    [
        'titre' => 'Balance-étiqueteuse',
        'aide'  => 'Fichiers à faire lire par DGI / RGI côté DFS.',
        'liens' => [
            ['dfs_articulo', 'Articles au format Articulo', 'sync_alt', null],
            ['dfs_articles', 'Articles (PLU / EAN)', 'sell',
             $nb_produits === null ? null : $nb_produits . ' produit(s) actif(s)'],
            ['dfs_lots', 'Lots fabriqués du jour', 'today',
             $nb_lots_jour === null ? null : $nb_lots_jour . ' lot(s) aujourd\'hui'],
        ],
    ],
];

require __DIR__ . '/includes/header.php';
?>

<h2 class="font-headline-lg text-2xl font-bold text-primary mb-1">Exports</h2>
<p class="text-sm text-on-surface-variant mb-6">
  Le téléchargement démarre au clic. Les fiches de traçabilité d'un lot précis
  s'exportent depuis l'écran <a href="tracabilite.php" class="text-primary underline font-semibold">Traçabilité</a>.
</p>

<?php foreach ($sections as $section): ?>
<section class="bg-surface rounded-xl border border-outline-variant p-5 mb-4">
  <h3 class="font-headline-md font-bold mb-1"><?= h($section['titre']) ?></h3>
  <p class="text-xs text-on-surface-variant mb-4"><?= h($section['aide']) ?></p>
  <div class="flex flex-col gap-2">
    <?php foreach ($section['liens'] as [$type, $libelle, $icone, $detail]): ?>
    <a href="export.php?type=<?= h($type) ?>"
       class="bg-surface-container-low rounded-xl px-4 py-3 flex items-center gap-3 hover:bg-surface-container">
      <span class="material-symbols-outlined text-primary shrink-0"><?= $icone ?></span>
      <span class="flex-1 min-w-0">
        <span class="block text-sm font-semibold"><?= h($libelle) ?></span>
        <?php if ($detail !== null): ?>
        <span class="block text-xs text-on-surface-variant"><?= h($detail) ?></span>
        <?php endif ?>
      </span>
      <span class="material-symbols-outlined text-on-surface-variant shrink-0">download</span>
    </a>
    <?php endforeach ?>
  </div>
</section>
<?php endforeach ?>

<?php if (est_admin()): ?>
<section class="bg-surface rounded-xl border border-outline-variant p-5">
  <h3 class="font-headline-md font-bold mb-1">Pont automatique</h3>
  <p class="text-xs text-on-surface-variant mb-3">
    Le PC de la balance récupère les fichiers Articulo tout seul, sans session, via un jeton.
    Le réglage vit dans les paramètres.
  </p>
  <a href="parametres.php" class="bg-surface-container text-on-surface rounded-full px-5 py-2.5 font-bold text-sm inline-flex items-center gap-2">
    <span class="material-symbols-outlined text-base">settings</span>Paramètres
  </a>
</section>
<?php endif ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

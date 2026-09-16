<?php
$page_active = 'guide';
$page_title  = 'Assistant balance';
require_once __DIR__ . '/includes/auth.php';
$moi = exiger_admin();

$pdo = db();

// ── État de préparation, lu en base
$nb_produits = 0; $nb_bovin = 0; $nb_compo_faire = 0;
if (referentiel_produits_pret()) {
    $nb_produits = (int)$pdo->query('SELECT COUNT(*) FROM produits WHERE actif=1')->fetchColumn();
    $nb_bovin    = (int)$pdo->query("SELECT COUNT(*) FROM produits WHERE actif=1 AND classe_traca='viande_bovine'")->fetchColumn();
    if (recettes_pretes()) {
        $nb_compo_faire = (int)$pdo->query(
            'SELECT COUNT(*) FROM produits p WHERE p.actif=1
             AND NOT EXISTS (SELECT 1 FROM recette_lignes r WHERE r.produit_id=p.id)'
        )->fetchColumn();
    }
}

$etapes = [
    [
        'ok'    => reglage('nom_atelier') !== '',
        'titre' => 'Nom de l\'atelier renseigné',
        'lien'  => 'parametres.php', 'action' => 'Paramètres',
    ],
    [
        'ok'    => reglage('agrement_atelier') !== '' && reglage('agrement_abattoir') !== '',
        'titre' => 'Numéros d\'agrément (abattoir + atelier de découpe)',
        'aide'  => 'Obligatoires sur les étiquettes de viande bovine.',
        'lien'  => 'parametres.php', 'action' => 'Paramètres',
    ],
    [
        'ok'    => reglage('code_certificateur') !== '',
        'titre' => 'Code de l\'organisme certificateur bio',
        'aide'  => 'Ex. FR-BIO-01. Nécessaire pour l\'Eurofeuille.',
        'lien'  => 'parametres.php', 'action' => 'Paramètres',
    ],
    [
        'ok'    => $nb_produits > 0,
        'titre' => $nb_produits . ' produit(s) créé(s)',
        'lien'  => 'produits.php', 'action' => 'Produits',
    ],
];
$pret = true;
foreach ($etapes as $e) { if (!$e['ok']) $pret = false; }

require __DIR__ . '/includes/header.php';
?>

<h2 class="font-headline-lg text-2xl font-bold text-primary mb-1">Assistant balance</h2>
<p class="text-sm text-on-surface-variant mb-6">
  Comment envoyer vos produits vers l'étiqueteuse Dibal, étape par étape.
</p>

<?php
// État de la dernière synchronisation remontée par l'agent (pont automatique)
$statut = json_decode(reglage('agent_statut') ?: '', true);
if (is_array($statut)):
    $ok = ($statut['etat'] ?? '') === 'ok';
    $quand = !empty($statut['le']) ? date('d/m/Y à H:i', strtotime($statut['le'])) : '—';
?>
<div class="rounded-xl p-4 mb-6 <?= $ok ? 'bg-primary-container text-on-primary-container' : 'bg-error-container text-on-error-container' ?>">
  <div class="flex items-center gap-2 font-bold">
    <span class="material-symbols-outlined"><?= $ok ? 'cloud_done' : 'cloud_off' ?></span>
    Synchronisation automatique — dernière : <?= h($quand) ?>
  </div>
  <div class="text-sm mt-1">
    <?= $ok
        ? 'OK — ' . ((int)($statut['nb'] ?? 0)) . ' produit(s) transmis à la balance.'
        : 'Échec — ' . h($statut['message'] ?? 'raison inconnue') ?>
  </div>
</div>
<?php endif ?>

<!-- 1. Où j'en suis -->
<section class="bg-surface rounded-xl border border-outline-variant p-5 mb-6">
  <h3 class="font-headline-md font-bold mb-4">1. Vérifier que tout est prêt</h3>
  <div class="flex flex-col gap-3">
    <?php foreach ($etapes as $e): ?>
    <div class="flex items-start gap-3">
      <span class="material-symbols-outlined <?= $e['ok'] ? 'text-primary' : 'text-error' ?>"><?= $e['ok'] ? 'check_circle' : 'radio_button_unchecked' ?></span>
      <div class="flex-1 min-w-0">
        <div class="text-sm font-semibold"><?= h($e['titre']) ?></div>
        <?php if (!empty($e['aide'])): ?><div class="text-xs text-on-surface-variant"><?= h($e['aide']) ?></div><?php endif ?>
      </div>
      <?php if (!$e['ok']): ?>
      <a href="<?= h($e['lien']) ?>" class="text-xs text-primary font-semibold shrink-0 mt-0.5"><?= h($e['action']) ?> →</a>
      <?php endif ?>
    </div>
    <?php endforeach ?>
  </div>
  <?php if ($nb_compo_faire > 0): ?>
  <div class="mt-4 bg-surface-container-low rounded-lg p-3 text-xs text-on-surface-variant">
    <?= $nb_compo_faire ?> produit(s) sans composition (préparations, plats) : ils fonctionnent, mais n'auront pas
    la mention bio tant que leur recette n'est pas saisie. Facultatif.
  </div>
  <?php endif ?>
</section>

<!-- 2. Exporter -->
<section class="bg-surface rounded-xl border border-outline-variant p-5 mb-6">
  <h3 class="font-headline-md font-bold mb-2">2. Exporter le fichier de la balance</h3>
  <p class="text-sm text-on-surface-variant mb-4">
    Ce fichier contient tous vos produits au format attendu par DFS (le logiciel de la balance).
  </p>
  <?php if ($pret): ?>
  <a href="export.php?type=dfs_articulo" class="inline-flex items-center gap-2 bg-primary text-on-primary rounded-full px-6 py-3 font-bold text-sm">
    <span class="material-symbols-outlined">download</span>Télécharger le fichier balance
  </a>
  <p class="text-xs text-on-surface-variant mt-3">
    Enregistrez-le sur l'ordinateur où est installé DFS (par clé USB ou e-mail si besoin).
  </p>
  <?php else: ?>
  <div class="bg-error-container text-on-error-container rounded-lg px-4 py-3 text-sm">
    Complétez d'abord l'étape 1 : certaines informations manquent pour un fichier exploitable.
  </div>
  <?php endif ?>
</section>

<!-- 3. Importer dans DFS -->
<section class="bg-surface rounded-xl border border-outline-variant p-5 mb-6">
  <h3 class="font-headline-md font-bold mb-2">3. Importer dans DFS (sur le PC de la balance)</h3>
  <p class="text-sm text-on-surface-variant mb-4">
    La première fois, il faut apprendre à DFS à lire notre fichier — c'est le rôle de <strong>DGI</strong>.
    Ensuite, l'import ne prend que quelques clics à chaque mise à jour.
  </p>
  <ol class="flex flex-col gap-3 text-sm">
    <?php
    $pas = [
      'Ouvrez DFS, puis le menu <strong>Programmation → Import/Export → DGI</strong>.',
      'Chargez le fichier téléchargé à l\'étape 2. Chaque colonne du fichier porte déjà le nom du champ DFS : associez-les une fois (PLU→PLU, EANScanner→EAN, Descripcion→libellé…).',
      'Seules deux colonnes demandent un choix manuel : <strong>CLASE_NOMBRE</strong> (la classe de traçabilité) et <strong>FAMILIA</strong> (la famille). Reliez-les à ce que vous avez créé dans DFS.',
      'Enregistrez ce modèle d\'import : les fois suivantes, DFS le réutilise tout seul.',
      'Lancez l\'import : les produits entrent dans DFS.',
      'Envoyez-les à la balance : menu <strong>Communication → Envoyer</strong>.',
    ];
    foreach ($pas as $i => $t): ?>
    <li class="flex gap-3">
      <span class="shrink-0 w-6 h-6 rounded-full bg-primary text-on-primary text-xs font-bold flex items-center justify-center"><?= $i + 1 ?></span>
      <span><?= $t ?></span>
    </li>
    <?php endforeach ?>
  </ol>
  <div class="mt-4 bg-surface-container-low rounded-lg p-3 text-xs text-on-surface-variant">
    Une fiche détaillée de correspondance des colonnes (<code>MAPPING_DFS.md</code>) a été fournie
    à votre installateur : c'est lui qui règle DGI la première fois. Ensuite, vous n'avez plus qu'à
    télécharger le fichier et relancer l'import.
  </div>
</section>

<!-- 4. Comprendre les mots -->
<section class="bg-surface rounded-xl border border-outline-variant p-5">
  <h3 class="font-headline-md font-bold mb-3">En clair : les mots de la balance</h3>
  <dl class="flex flex-col gap-3 text-sm">
    <?php foreach ([
      ['DFS', 'Le logiciel installé sur le PC, qui pilote la balance-étiqueteuse Dibal.'],
      ['DGI', 'L\'outil de DFS qui apprend à lire un fichier extérieur (le nôtre). On le règle une seule fois.'],
      ['RGI', 'Le mécanisme de DFS qui rejoue l\'import automatiquement les fois suivantes.'],
      ['PLU', 'Le numéro court à 4 chiffres qui identifie un produit sur la balance.'],
      ['Classe de traçabilité', 'Le modèle des mentions imprimées (origine viande bovine, etc.). À créer une fois dans DFS.'],
    ] as [$mot, $def]): ?>
    <div>
      <dt class="font-semibold text-primary"><?= h($mot) ?></dt>
      <dd class="text-on-surface-variant"><?= h($def) ?></dd>
    </div>
    <?php endforeach ?>
  </dl>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

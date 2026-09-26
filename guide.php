<?php
$page_active = 'guide';
$page_title  = 'Assistant balance';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/dfs.php';
// L'accompagnement n'administre rien : il explique. Le réserver aux
// administrateurs privait de la marche à suivre ceux qui font le travail.
//
// Les réglages décrits ici (DGI, RGI, DLD) viennent des manuels Dibal :
// 49-MDRGI000EN10 (DGI / RGI) et 49-MDLD500EN08 (DLD), en anglais. Les
// écrans sont désignés en français, leur nom anglais en repère (ECRANS).
$moi = exiger_connexion();
$peut_regler = est_admin();

$pdo = db();

// ── État de préparation, lu en base
$nb_produits = 0; $nb_compo_faire = 0;
if (referentiel_produits_pret()) {
    $nb_produits = (int)$pdo->query('SELECT COUNT(*) FROM produits WHERE actif=1')->fetchColumn();
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
        'titre' => $nb_produits . ' produit(s) actif(s)',
        'lien'  => 'produits.php', 'action' => 'Produits',
    ],
];

// Le fichier tel qu'il partira : ce qui a dû être coupé ou manque, et
// une ligne réelle pour montrer à quoi ressemble chaque champ dans DGI.
$fichier = $nb_produits > 0 ? dfs_articles() : ['titres' => [], 'lignes' => [], 'alertes' => []];
$exemple = $fichier['lignes'][0] ?? [];
$colonnes = DFS_COLONNES;
$format_etiquette = reglage('dfs_format_etiquette');
if ($format_etiquette !== '') $colonnes[] = DFS_COLONNE_FORMAT;

$statut = json_decode(reglage('agent_statut') ?: '', true);

// Une liste d'étapes numérotées. Les textes sont écrits ici, pas saisis :
// ils peuvent porter du HTML (gras, code).
// $depart : pour reprendre la numérotation après un tableau intercalé.
function liste_pas(array $pas, int $depart = 1): void {
    echo '<ol class="flex flex-col gap-3 text-sm">';
    foreach ($pas as $i => $t) {
        echo '<li class="flex gap-3"><span class="shrink-0 w-6 h-6 rounded-full bg-primary text-on-primary text-xs font-bold flex items-center justify-center">'
           . ($depart + $i) . '</span><span class="min-w-0">' . $t . '</span></li>';
    }
    echo '</ol>';
}

// Un tableau « réglage → valeur » ; le réglage et la valeur peuvent porter du HTML.
function tableau_reglages(array $lignes): void {
    echo '<div class="overflow-x-auto"><table class="w-full text-sm"><tbody>';
    foreach ($lignes as [$quoi, $valeur, $pourquoi]) {
        echo '<tr class="border-t border-outline-variant align-top">'
           . '<td class="py-2 pr-3">' . $quoi . '</td>'
           . '<td class="py-2"><div>' . $valeur . '</div>'
           . ($pourquoi !== '' ? '<div class="text-xs text-on-surface-variant">' . h($pourquoi) . '</div>' : '')
           . '</td></tr>';
    }
    echo '</tbody></table></div>';
}

// ── Intitulés des écrans Dibal (DGI, RGI, DLD).
//    Les manuels fournis sont en anglais, les postes de l'atelier en français.
//    Chaque élément est donc désigné par ce qu'il fait, en français, suivi de
//    son nom dans le manuel, entre guillemets, pour le retrouver à l'écran.
//    Quand on aura relevé les intitulés français exacts, c'est ici seulement
//    qu'ils se remplacent.
const ECRANS = [
    // DGI
    'Design General Integration' => 'DGI',
    'Imports'                    => 'Importations',
    'Name'                       => 'Nom',
    'File type'                  => 'Type de fichier',
    'Initial line'               => 'Ligne de départ',
    'Fields separator'           => 'Séparateur de champs',
    'Operation type'             => 'Type d\'opération',
    'Data to be added/eliminated'=> 'Ajouts / suppressions',
    'Load file'                  => 'Dossier du fichier',
    'File to import'             => 'Fichier à importer',
    'Generate List of Fields'    => 'Générer la liste des champs',
    'Continue'                   => 'Continuer',
    'Import configuration'       => 'Configuration des importations',
    'Communicate importation with scales selected in DFS' => 'Importer seulement vers les balances sélectionnées dans DFS',
    'Activating imports'         => 'Activation des importations',
    // Champs DFS et types, dans la liste de DGI
    'Code'                       => 'Code',
    'Type'                       => 'Type',
    'Name 2'                     => 'Nom 2',
    'Price'                      => 'Prix',
    'Expiration days'            => 'Jours de péremption',
    'G Text'                     => 'Texte G',
    'Label format'               => 'Format d\'étiquette',
    'Numeric'                    => 'Numérique',
    'Text'                       => 'Texte',
    'Numeric with dot as decimal mark' => 'Numérique, point décimal',
    // RGI
    'Run General Integration'    => 'RGI',
    'Show status'                => 'Afficher l\'état',
    'Show report'                => 'Afficher le rapport',
    'Sending modifications'      => 'Envoyer les modifications',
    // DLD
    'New Label'                  => 'Nouvelle étiquette',
    'Items'                      => 'Articles',
    'Item name'                  => 'Nom de l\'article',
    'Text G'                     => 'Texte G',
    'Expiry date'                => 'Date de péremption',
    'Bar Codes'                  => 'Codes-barres',
    'Shipping'                   => 'Envoi',
    'Send'                       => 'Envoyer',
];

// Désignation en clair ; « Text 01 » et consorts suivent la même règle.
function ecran_fr(string $en): string {
    if (isset(ECRANS[$en])) return ECRANS[$en];
    return preg_match('/^Text (\d+)$/', $en, $m) ? 'Texte ' . $m[1] : $en;
}

// En gras la désignation française, puis le nom du manuel en petit.
function ecran(string $en): string {
    return '<strong>' . h(ecran_fr($en)) . '</strong>'
         . ' <span class="text-xs text-on-surface-variant whitespace-nowrap">« ' . h($en) . ' »</span>';
}

$dossier = DFS_DOSSIER;

require __DIR__ . '/includes/header.php';
?>

<h2 class="font-headline-lg text-2xl font-bold text-primary mb-1">Assistant balance</h2>
<p class="text-sm text-on-surface-variant mb-4">
  De l'application à l'étiquette imprimée. Les réglages sur le PC de la balance
  ne se font qu'une fois ; ensuite, vos produits arrivent sur la balance tout seuls.
</p>

<!-- Le parcours du fichier, d'un coup d'œil -->
<div class="flex flex-wrap items-center gap-1 text-xs mb-6">
  <?php foreach (['Application', 'ARTICLES.TXT', $dossier, 'RGI', 'DFS', 'Balance'] as $i => $etape): ?>
    <?php if ($i): ?><span class="material-symbols-outlined text-base text-on-surface-variant">arrow_forward</span><?php endif ?>
    <span class="bg-surface-container rounded-full px-3 py-1 font-semibold"><?= h($etape) ?></span>
  <?php endforeach ?>
</div>

<?php
// État de la dernière synchronisation remontée par l'agent (pont automatique)
if (is_array($statut)):
    $ok = ($statut['etat'] ?? '') === 'ok';
    $quand = !empty($statut['le']) ? date('d/m/Y à H:i', strtotime($statut['le'])) : '—';
?>
<div class="rounded-xl p-4 mb-6 <?= $ok ? 'bg-primary-container text-on-primary-container' : 'bg-error-container text-on-error-container' ?>">
  <div class="flex items-center gap-2 font-bold">
    <span class="material-symbols-outlined"><?= $ok ? 'cloud_done' : 'cloud_off' ?></span>
    Synchronisation avec la balance — dernière : <?= h($quand) ?>
  </div>
  <div class="text-sm mt-1">
    <?= $ok
        ? 'OK — ' . ((int)($statut['nb'] ?? 0)) . ' produit(s) déposé(s) pour RGI.'
        : 'Échec — ' . h($statut['message'] ?? 'raison inconnue') ?>
  </div>
  <?php if (!$ok && stripos($statut['message'] ?? '', 'inattendue') !== false): ?>
  <div class="text-xs mt-2">
    Si l'agent a été installé avant la version 2.7, il ne reconnaît pas le nouveau fichier :
    retéléchargez-le à l'étape 2 et relancez <code>installer.bat</code>.
  </div>
  <?php endif ?>
  <div class="text-xs mt-2 opacity-80">
    Pour envoyer sans attendre : sur le PC de la balance, double-cliquez sur
    <code>maj_dfs.bat</code> dans le dossier de l'agent.
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
      <?php if (!$e['ok'] && $peut_regler): ?>
      <a href="<?= h($e['lien']) ?>" class="text-xs text-primary font-semibold shrink-0 mt-0.5"><?= h($e['action']) ?> →</a>
      <?php elseif (!$e['ok']): ?>
      <span class="text-xs text-on-surface-variant shrink-0 mt-0.5" title="Réglage réservé aux administrateurs">à faire régler</span>
      <?php endif ?>
    </div>
    <?php endforeach ?>
  </div>
  <?php if ($nb_compo_faire > 0): ?>
  <div class="mt-4 bg-surface-container-low rounded-lg p-3 text-xs text-on-surface-variant">
    <?= $nb_compo_faire ?> produit(s) sans composition (préparations, plats) : ils fonctionnent, mais n'auront ni
    liste d'ingrédients ni mention bio tant que leur recette n'est pas saisie. Facultatif.
  </div>
  <?php endif ?>
  <?php if ($fichier['alertes']): ?>
  <div class="mt-4 bg-surface-container-low rounded-lg p-3 text-xs">
    <div class="font-bold mb-1 flex items-center gap-1">
      <span class="material-symbols-outlined text-base text-error">warning</span>
      Ce que la balance recevra incomplet
    </div>
    <ul class="list-disc pl-5 flex flex-col gap-1 text-on-surface-variant">
      <?php foreach ($fichier['alertes'] as $a): ?><li><?= h($a) ?></li><?php endforeach ?>
    </ul>
    <?php if ($peut_regler): ?>
    <div class="mt-2"><a href="produits.php" class="text-primary font-semibold">Produits →</a>
      · <a href="parametres.php" class="text-primary font-semibold">Paramètres →</a></div>
    <?php endif ?>
  </div>
  <?php endif ?>
</section>

<!-- 2. Récupérer le fichier -->
<section id="recuperer" class="bg-surface rounded-xl border border-outline-variant p-5 mb-6">
  <h3 class="font-headline-md font-bold mb-2">2. Mettre le fichier sur le PC de la balance</h3>
  <p class="text-sm text-on-surface-variant mb-4">
    DFS lit vos produits dans un fichier nommé <strong>ARTICLES.TXT</strong>, déposé dans le dossier
    <code><?= h($dossier) ?></code> du PC relié à la balance. Deux façons de l'y mettre :
  </p>

  <div class="grid md:grid-cols-2 gap-4">
    <div class="bg-surface-container-low rounded-xl p-4">
      <div class="font-bold mb-1 flex items-center gap-2">
        <span class="material-symbols-outlined text-primary">autorenew</span>Automatique — conseillé
      </div>
      <p class="text-xs text-on-surface-variant mb-3">
        Un petit programme, l'agent, récupère le fichier toutes les 2 minutes dès qu'un produit change.
        À installer une fois, par un administrateur.
      </p>
      <?php if ($peut_regler): ?>
      <form method="post" action="agent.php" class="mb-3">
        <button class="inline-flex items-center gap-2 bg-primary text-on-primary rounded-full px-5 py-2.5 font-bold text-sm">
          <span class="material-symbols-outlined text-base">download</span>Télécharger l'agent
        </button>
      </form>
      <?php liste_pas([
        'Copiez <code>agent-balance.zip</code> sur le PC de la balance, clic droit → <strong>Extraire tout</strong>.',
        'Dans le dossier extrait, clic droit sur <code>installer.bat</code> → <strong>Exécuter en tant qu\'administrateur</strong>. '
          . 'Si Windows affiche « Windows a protégé votre ordinateur » : <em>Informations complémentaires</em> → <em>Exécuter quand même</em>.',
        'Le lien vers ce site est déjà dans le zip. Aux deux questions suivantes, <strong>Entrée</strong> suffit : '
          . 'dossier <code>' . h($dossier) . '</code>, et pas de sauvegarde de la base DFS (facultative).',
        'La fenêtre affiche <strong>OK</strong> : c\'est installé. Le résultat apparaîtra aussi en haut de cette page.',
      ]) ?>
      <p class="text-xs text-on-surface-variant mt-3">
        Le zip contient la clé d'accès au site : ne le transmettez pas. Pour la couper :
        <a href="parametres.php" class="text-primary underline">Paramètres → Pont automatique → Révoquer</a>.
      </p>
      <?php else: ?>
      <p class="text-xs bg-surface-container rounded-lg p-3">
        Le téléchargement de l'agent est réservé aux administrateurs : il contient la clé d'accès au site.
      </p>
      <?php endif ?>
    </div>

    <div class="bg-surface-container-low rounded-xl p-4">
      <div class="font-bold mb-1 flex items-center gap-2">
        <span class="material-symbols-outlined text-primary">touch_app</span>À la main
      </div>
      <p class="text-xs text-on-surface-variant mb-3">
        Sans installer l'agent : à refaire après chaque changement de produit ou de prix.
        Il vous faut aussi ce fichier une fois pour régler DGI (étape 3).
      </p>
      <a href="export.php?type=dfs_articulo" class="inline-flex items-center gap-2 bg-primary text-on-primary rounded-full px-5 py-2.5 font-bold text-sm mb-3">
        <span class="material-symbols-outlined text-base">download</span>Télécharger ARTICLES.TXT
      </a>
      <?php liste_pas([
        'Téléchargez le fichier depuis le PC de la balance (ou apportez-le par clé USB).',
        'Déplacez-le dans <code>' . h($dossier) . '</code> (créez le dossier la première fois).',
        'RGI l\'importe dans l\'instant, s\'il est lancé (étape 4). Le fichier disparaît alors du dossier : c\'est normal.',
      ]) ?>
    </div>
  </div>
</section>

<!-- 3. DGI -->
<section class="bg-surface rounded-xl border border-outline-variant p-5 mb-6">
  <h3 class="font-headline-md font-bold mb-2">3. Apprendre à DFS à lire le fichier <span class="text-on-surface-variant font-normal text-sm">— DGI, une seule fois</span></h3>
  <p class="text-sm text-on-surface-variant mb-3">
    DGI est livré avec DFS. On y décrit une fois le fichier ; RGI s'en sert ensuite à chaque import.
    Gardez sous la main un ARTICLES.TXT téléchargé à l'étape 2.
  </p>
  <p class="text-xs bg-surface-container-low rounded-lg p-3 mb-4">
    Votre DFS est en français, les manuels Dibal en anglais. Chaque écran et chaque réglage est donc
    désigné ici par ce qu'il fait, suivi entre guillemets de son nom dans le manuel : cherchez à l'écran
    l'intitulé français qui y correspond.
  </p>
  <?php liste_pas([
    'Menu Démarrer → dossier <strong>Dibal DFS</strong> → raccourci de DGI ' . ecran('Design General Integration') . '. '
      . 'Identifiant <code>general</code>, mot de passe <code>general</code> (valeurs d\'usine).',
    'Menu des ' . ecran('Imports') . ' → bouton de création, puis remplissez :',
  ]) ?>
  <div class="mt-3 mb-4 pl-9">
    <?php tableau_reglages([
      [ecran('Name'),             'TracaBoucher', ''],
      [ecran('File type'),        'Articles', ''],
      [ecran('Initial line'),     '<strong>1</strong>', 'La ligne 0 porte les titres des colonnes : on la saute.'],
      [ecran('Fields separator'), '<strong>;</strong> (point-virgule)', ''],
      [ecran('Operation type'),   ecran('Data to be added/eliminated'), 'Crée les nouveaux produits, met à jour les autres.'],
      [ecran('Load file'),        '<code>' . h($dossier) . '</code>', 'Le dossier que RGI surveille.'],
      [ecran('File to import'),   '<code>ARTICLES*.TXT</code>', 'L\'étoile accepte aussi « ARTICLES (1).TXT », si le navigateur a renommé le fichier.'],
    ]) ?>
  </div>
  <?php liste_pas([
    'Juste en dessous, choisissez votre ARTICLES.TXT, puis ' . ecran('Generate List of Fields') . ' → ' . ecran('Continue') . '.',
    'DGI affiche les champs numérotés à partir de 0, avec les valeurs de votre premier produit. '
      . 'Pour chaque numéro, choisissez le champ DFS et le type du tableau ci-dessous :',
  ], 3) ?>
  <div class="mt-3 mb-4 overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="text-left text-xs text-on-surface-variant">
          <th class="py-2 pr-3">N°</th><th class="py-2 pr-3">Dans votre fichier</th>
          <th class="py-2 pr-3">Champ DFS à choisir</th><th class="py-2">Type</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($colonnes as $i => [$champ, $type, $contenu]):
            $v = (string)($exemple[$i] ?? '');
            if (mb_strlen($v) > 28) $v = mb_substr($v, 0, 27) . '…'; ?>
        <tr class="border-t border-outline-variant align-top">
          <td class="py-2 pr-3 font-bold"><?= $i ?></td>
          <td class="py-2 pr-3">
            <div><?= h($contenu) ?></div>
            <div class="text-xs text-on-surface-variant"><?= $v === '' ? '<em>vide pour ce produit</em>' : 'ex. ' . h($v) ?></div>
          </td>
          <td class="py-2 pr-3">
            <div class="font-semibold"><?= h(ecran_fr($champ)) ?></div>
            <div class="text-xs text-on-surface-variant">« <?= h($champ) ?> »</div>
          </td>
          <td class="py-2 text-xs <?= $champ === 'Price' ? 'font-bold text-error' : 'text-on-surface-variant' ?>">
            <div><?= h(ecran_fr($type)) ?></div>
            <?php if (ecran_fr($type) !== $type): ?><div class="font-normal">« <?= h($type) ?> »</div><?php endif ?>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
    <p class="text-xs text-on-surface-variant mt-2">
      Le type du prix est le seul piège : le fichier écrit les prix avec un point (11.30). Il faut le type
      numérique <strong>avec point décimal</strong>, pas avec virgule ni « selon la configuration régionale »,
      sinon DFS les lirait mal. Les champs Texte restés vides n'impriment rien.
    </p>
  </div>
  <?php liste_pas([
    'Menu ' . ecran('Import configuration') . ' : import actif <strong>toute la journée</strong>. '
      . 'Laissez décochée la case ' . ecran('Communicate importation with scales selected in DFS')
      . ' : cochée sans balance sélectionnée dans DFS, rien ne serait importé.',
    'Menu ' . ecran('Activating imports') . ' : cochez l\'import <strong>TracaBoucher</strong>. C\'est fini pour DGI.',
  ], 5) ?>
</section>

<!-- 4. RGI -->
<section class="bg-surface rounded-xl border border-outline-variant p-5 mb-6">
  <h3 class="font-headline-md font-bold mb-2">4. Laisser RGI importer <span class="text-on-surface-variant font-normal text-sm">— à laisser tourner</span></h3>
  <p class="text-sm text-on-surface-variant mb-4">
    RGI surveille <code><?= h($dossier) ?></code> : dès qu'un ARTICLES.TXT y arrive, il l'enregistre dans DFS
    et l'envoie à la balance.
  </p>
  <?php liste_pas([
    'Menu Démarrer → dossier <strong>Dibal DFS</strong> → raccourci de RGI ' . ecran('Run General Integration') . '. Une icône apparaît près de l\'horloge.',
    'Clic droit sur l\'icône → ' . ecran('Show status') . ' : RGI indique qu\'il attend le fichier (« WAITING… » dans le manuel), '
      . 'puis qu\'il l\'importe et l\'envoie à la balance (« IMPORTING… »).',
    'Une fois importé, le fichier quitte <code>' . h($dossier) . '</code> (une copie va dans le dossier <em>ProcessedFiles</em>) : c\'est le signe que tout s\'est bien passé.',
    'Pour que RGI démarre avec Windows : touches <strong>Windows + R</strong>, tapez <code>shell:startup</code>, et copiez dans ce dossier le raccourci de RGI.',
    'Une balance était éteinte pendant l\'envoi ? Une fois rallumée : clic droit sur l\'icône → ' . ecran('Sending modifications') . '.',
  ]) ?>
</section>

<!-- 5. DLD -->
<section class="bg-surface rounded-xl border border-outline-variant p-5 mb-6">
  <h3 class="font-headline-md font-bold mb-2">5. Préparer l'étiquette <span class="text-on-surface-variant font-normal text-sm">— DLD, une seule fois</span></h3>
  <p class="text-sm text-on-surface-variant mb-4">
    DLD, l'éditeur d'étiquettes de DFS, dessine ce que la balance imprime. Chaque champ reçu de
    l'application y a sa place.
  </p>
  <?php liste_pas([
    'Menu Démarrer → <strong>DFS Applications</strong> → <strong>DLD</strong> (ou depuis DFS). '
      . 'Nouvelle étiquette ' . ecran('New Label') . ' : modèle <em>500 Range/D900</em>, en millimètres, largeur de votre étiquette (60 mm au plus).',
    'Glissez sur l\'étiquette les champs dont vous avez besoin :',
  ]) ?>
  <div class="mt-3 mb-4 pl-9">
    <?php tableau_reglages([
      ['<strong>Nom du produit</strong>', ecran('Items') . ' → ' . ecran('Item name') . ' et ' . ecran('Name 2'), 'Deux lignes de 20 caractères.'],
      ['<strong>Ingrédients</strong>',    ecran('Items') . ' → ' . ecran('Text G'), ''],
      ['<strong>Origine (bovin)</strong>', ecran('Items') . ' → ' . ecran('Text 1') . ' à ' . ecran('Text 6'), 'Origine ou Né / Élevé, Abattu, agrément, Découpé, agrément.'],
      ['<strong>Bio</strong>',            'Logos → l\'Eurofeuille, puis ' . ecran('Items') . ' → ' . ecran('Text 7') . ' et ' . ecran('Text 8') . ' dessous',
       'Code de l\'organisme et origine agricole. Logo : largeur multiple de 8, 432 pixels au plus.'],
      ['<strong>DLC</strong>',            'Dates → ' . ecran('Expiry date'), 'Calculée par la balance avec la durée de vie.'],
      ['<strong>Poids, prix</strong>',    'Poids, prix au kg, prix à payer (« Weight », « Price », « Amount »)', ''],
      ['<strong>Code-barres</strong>',    ecran('Bar Codes') . ' → <strong>EAN 13</strong>', ''],
    ]) ?>
  </div>
  <?php liste_pas([
    'Onglet ' . ecran('Shipping') . ' : ' . ecran('Label format') . ' = un numéro à partir de <strong>21</strong> (par exemple 21), cochez la balance, puis ' . ecran('Send') . '.',
    $format_etiquette !== ''
      ? 'Le n° <strong>' . h($format_etiquette) . '</strong> est réglé dans l\'application : il part avec chaque produit. Dans DGI, associez aussi le champ n° '
        . (count($colonnes) - 1) . ' à ' . ecran('Label format') . '.'
      : 'Pour que tous les produits utilisent cette étiquette, reportez son numéro dans '
        . ($peut_regler ? '<a href="parametres.php#balance" class="text-primary underline">Paramètres → Balance-étiqueteuse</a>' : 'Paramètres (administrateur)')
        . ' : une colonne « Label format » s\'ajoute au fichier, à associer dans DGI.',
  ], 3) ?>
  <p class="text-xs text-on-surface-variant mt-4">
    DLD fonctionne 90 jours à l'essai, puis demande une licence Dibal
    (dibal@dibal.com · +34 94 452 15 10). La licence est liée à un PC.
  </p>
</section>

<!-- 6. En cas de souci -->
<details class="bg-surface rounded-xl border border-outline-variant p-5 mb-6">
  <summary class="cursor-pointer font-headline-md font-bold">En cas de souci</summary>
  <dl class="flex flex-col gap-4 text-sm mt-4">
    <?php foreach ([
      ['Rien ne se passe', 'RGI est-il lancé (icône près de l\'horloge) ? L\'import TracaBoucher est-il coché dans ' . ecran('Activating imports') . ' ? '
        . 'Le fichier est-il bien dans <code>' . h($dossier) . '</code> ?'],
      ['Un produit manque sur la balance', 'RGI écarte une ligne dont une donnée est invalide et envoie les autres. La raison (ligne, champ) est dans '
        . ecran('Show report') . ', dans le dossier <code>RGI\\Reports</code> et dans <code>RGI\\Logs</code>. Les limites connues sont signalées à l\'étape 1.'],
      ['Prix faux sur la balance', 'Dans DGI, le type du champ ' . ecran('Price') . ' doit être ' . ecran('Numeric with dot as decimal mark') . ' : le fichier écrit 11.30, avec un point.'],
      ['Accents faux (« Ã© »)', ($peut_regler ? '<a href="parametres.php#balance" class="text-primary underline">Paramètres → Balance-étiqueteuse</a>' : 'Paramètres')
        . ' → Encodage : <em>Windows (ANSI)</em>. Puis renvoyez le fichier.'],
      ['Les titres des colonnes apparaissent comme un produit', 'Dans DGI, ' . ecran('Initial line') . ' doit valoir 1.'],
      ['Un produit retiré ici reste sur la balance', 'Le fichier ne contient que les produits actifs : supprimez l\'ancien article dans DFS.'],
      ['L\'agent indique un échec', 'La raison s\'affiche en haut de cette page ; le détail est dans <code>agent-balance.log</code>, dans le dossier de l\'agent.'],
      ['Changement de PC', 'DGI garde son réglage dans <code>LineData.xml</code> : copié dans le dossier de RGI du nouveau PC, il évite de refaire l\'étape 3.'],
    ] as [$souci, $reponse]): ?>
    <div>
      <dt class="font-semibold"><?= h($souci) ?></dt>
      <dd class="text-on-surface-variant"><?= $reponse ?></dd>
    </div>
    <?php endforeach ?>
  </dl>
</details>

<!-- Comprendre les mots -->
<details class="bg-surface rounded-xl border border-outline-variant p-5">
  <summary class="cursor-pointer font-headline-md font-bold">En clair : les mots de la balance</summary>
  <dl class="flex flex-col gap-3 text-sm mt-4">
    <?php foreach ([
      ['DFS', 'Le logiciel installé sur le PC, qui pilote la balance-étiqueteuse Dibal.'],
      ['ARTICLES.TXT', 'Le fichier de vos produits que DFS sait lire. L\'application le fabrique ; vous n\'avez jamais à l\'ouvrir.'],
      ['DGI', 'L\'outil de DFS où l\'on décrit une fois le fichier : quelle colonne va dans quel champ.'],
      ['RGI', 'Le programme de DFS qui guette le fichier et l\'importe tout seul, puis l\'envoie à la balance.'],
      ['DLD', 'L\'outil de DFS qui dessine l\'étiquette.'],
      ['Agent', 'Le petit programme de l\'application qui dépose le fichier sur le PC, sans que vous ayez à le télécharger.'],
      ['PLU', 'Le numéro court qui identifie un produit sur la balance.'],
      ['Format d\'étiquette', 'Le numéro sous lequel une étiquette dessinée dans DLD est rangée dans la balance.'],
    ] as [$mot, $def]): ?>
    <div>
      <dt class="font-semibold text-primary"><?= h($mot) ?></dt>
      <dd class="text-on-surface-variant"><?= h($def) ?></dd>
    </div>
    <?php endforeach ?>
  </dl>
</details>

<?php require __DIR__ . '/includes/footer.php'; ?>

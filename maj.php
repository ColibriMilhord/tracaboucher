<?php
/**
 * Applique les fichiers SQL du dossier migrations/.
 * Réservé aux administrateurs : contrairement à install.php, ce fichier peut
 * rester sur le serveur sans risque.
 * Les erreurs « existe déjà » sont ignorées, on peut donc le relancer.
 */
$page_active = 'maj';
$page_title  = 'Mise à jour de la base';
require_once __DIR__ . '/includes/auth.php';
$moi = exiger_admin();

$pdo     = db();
$dossier = __DIR__ . '/migrations';
$fichiers = is_dir($dossier) ? glob($dossier . '/*.sql') : [];
sort($fichiers);

// Codes MySQL signalant que l'objet existe déjà — la migration est déjà passée.
const DEJA_FAIT = [1050, 1060, 1061, 1022, 1826, 1091];

// Ajouter une clé étrangère recrée la table : MariaDB signale alors un nom de
// contrainte déjà pris par un 1005 (errno 121) et non par le 1826 attendu.
function deja_applique(PDOException $e, string $stmt): bool {
    $code = $e->errorInfo[1] ?? 0;
    if (in_array($code, DEJA_FAIT, true)) return true;
    return $code === 1005 && stripos($stmt, 'ADD CONSTRAINT') !== false;
}

$rapport = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appliquer'])) {
    foreach ($fichiers as $f) {
        $sql = preg_replace('/^\s*--.*$/m', '', (string)file_get_contents($f));
        $instructions = array_filter(array_map('trim', explode(';', $sql)), fn($s) => $s !== '');
        $faits = 0; $ignores = 0; $erreur = null;
        foreach ($instructions as $stmt) {
            try {
                $pdo->exec($stmt);
                $faits++;
            } catch (PDOException $e) {
                if (deja_applique($e, $stmt)) { $ignores++; continue; }
                $erreur = $e->getMessage();
                break;
            }
        }
        $rapport[] = ['nom' => basename($f), 'faits' => $faits, 'ignores' => $ignores, 'erreur' => $erreur];
    }
}

// État des tables et des colonnes dont l'application a besoin. Une table peut
// exister sans être complète si une exécution s'est interrompue en route :
// on le montre ici plutôt que de laisser l'erreur surgir à la saisie.
$attendu = [
    'utilisateurs'        => ['identifiant', 'nom', 'mot_de_passe', 'role', 'actif'],
    'types_matiere'       => ['code', 'libelle', 'categorie', 'actif'],
    'lots_entree'         => ['num_lot', 'type_id', 'date_entree', 'temperature', 'fournisseur',
                              'num_animal', 'pays_naissance', 'pays_elevage', 'pays_abattage', 'agrement_abattoir'],
    'lots_sortie'         => ['num_lot', 'date_fabrication', 'produit', 'produit_id', 'quantite',
                              'conditionnement', 'conservation'],
    'lots_sortie_entrees' => ['sortie_id', 'entree_id', 'quantite_kg'],
    'reglages'            => ['cle', 'valeur'],
    'produits'            => ['plu', 'ean13', 'libelle', 'classe_traca', 'dlc_jours'],
    'recette_lignes'      => ['produit_id', 'libelle', 'quantite', 'unite', 'nature', 'bio', 'ordre'],
];

$etat = [];
$q_col = $pdo->prepare('SELECT column_name FROM information_schema.columns
                        WHERE table_schema = DATABASE() AND table_name = ?');
foreach ($attendu as $table => $colonnes) {
    $q_col->execute([$table]);
    $presentes = $q_col->fetchAll(PDO::FETCH_COLUMN);
    $etat[$table] = [
        'existe'    => (bool)$presentes,
        'manquants' => $presentes ? array_values(array_diff($colonnes, $presentes)) : [],
    ];
}
$tout_ok = true;
foreach ($etat as $e) { if (!$e['existe'] || $e['manquants']) $tout_ok = false; }

require __DIR__ . '/includes/header.php';
?>

<h2 class="font-headline-lg text-2xl font-bold text-primary mb-1">Mise à jour de la base</h2>
<p class="text-sm text-on-surface-variant mb-6">
  Applique les évolutions de schéma livrées avec l'application. Sans effet si elles sont déjà en place.
</p>

<?php foreach ($rapport as $r): ?>
<div class="rounded-xl px-4 py-3 mb-3 text-sm <?= $r['erreur'] ? 'bg-error-container text-on-error-container' : 'bg-primary-container text-on-primary-container' ?>">
  <strong><?= h($r['nom']) ?></strong> —
  <?php if ($r['erreur']): ?>
    échec : <?= h($r['erreur']) ?>
  <?php else: ?>
    <?= $r['faits'] ?> instruction(s) appliquée(s)<?= $r['ignores'] ? ', ' . $r['ignores'] . ' déjà en place' : '' ?>.
  <?php endif ?>
</div>
<?php endforeach ?>

<div class="bg-surface rounded-xl border border-outline-variant p-5 mb-5">
  <h3 class="font-headline-md font-bold mb-1">État de la base</h3>
  <p class="text-xs text-on-surface-variant mb-3">
    <?= $tout_ok
        ? 'Toutes les tables et colonnes attendues sont en place.'
        : 'Des éléments manquent : appliquez les migrations ci-dessous.' ?>
  </p>
  <div class="grid sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
    <?php foreach ($etat as $t => $e):
      $ok = $e['existe'] && !$e['manquants']; ?>
    <div class="flex items-start gap-2">
      <span class="material-symbols-outlined text-base <?= $ok ? 'text-primary' : 'text-error' ?>"><?= $ok ? 'check_circle' : 'cancel' ?></span>
      <div class="min-w-0">
        <code><?= h($t) ?></code>
        <?php if (!$e['existe']): ?>
        <div class="text-xs text-error">table absente</div>
        <?php elseif ($e['manquants']): ?>
        <div class="text-xs text-error">colonnes manquantes : <?= h(implode(', ', $e['manquants'])) ?></div>
        <?php endif ?>
      </div>
    </div>
    <?php endforeach ?>
  </div>
</div>

<div class="bg-surface rounded-xl border border-outline-variant p-5">
  <h3 class="font-headline-md font-bold mb-3">Fichiers de migration</h3>
  <?php if (!$fichiers): ?>
  <p class="text-sm text-on-surface-variant">Aucun fichier dans <code>migrations/</code>.</p>
  <?php else: ?>
  <ul class="text-sm mb-4 flex flex-col gap-1">
    <?php foreach ($fichiers as $f): ?>
    <li class="flex items-center gap-2"><span class="material-symbols-outlined text-base text-on-surface-variant">description</span><code><?= h(basename($f)) ?></code></li>
    <?php endforeach ?>
  </ul>
  <form method="post">
    <button name="appliquer" value="1" class="bg-primary text-on-primary rounded-full px-6 py-3 font-bold text-sm">
      Appliquer les migrations
    </button>
  </form>
  <?php endif ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

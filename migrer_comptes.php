<?php
// ============================================================
//  Reprise des comptes locaux dans la base partagée CAUSSELOT.
//
//  Depuis la connexion unique, l'authentification lit la table
//  `utilisateurs` de la base causselot. Les comptes qui n'existaient que
//  dans TraçaBoucher doivent y être recopiés, sinon leurs titulaires ne
//  peuvent plus se connecter. Le mot de passe est repris tel quel : c'est
//  une empreinte password_hash(), elle reste valable.
//
//  Page à usage unique, relançable sans risque : un identifiant déjà
//  présent dans la base partagée n'est jamais écrasé.
// ============================================================
$page_active = 'utilisateurs';
$page_title  = 'Reprise des comptes';
require_once __DIR__ . '/includes/auth.php';
$moi = exiger_admin();
$pdo = db();

// Les rôles ne portent pas le même vocabulaire de part et d'autre.
function role_cible(string $role_local): string {
    return $role_local === 'admin' ? 'admin' : 'atelier';
}

$table_locale  = '`' . DB_NAME . '`.utilisateurs';
$table_partagee = '`' . DB_NAME_CAUSSELOT . '`.utilisateurs';

$configure = DB_NAME_CAUSSELOT !== '' && DB_NAME_CAUSSELOT !== DB_NAME;
$erreur = '';
$rapport = null;
$comptes = [];

if ($configure) {
    try {
        $locaux = $pdo->query("SELECT id, identifiant, nom, mot_de_passe, role, actif FROM $table_locale ORDER BY nom")->fetchAll();

        $existants = $pdo->query("SELECT identifiant FROM $table_partagee")->fetchAll(PDO::FETCH_COLUMN);
        $existants = array_map('strval', $existants);

        foreach ($locaux as $u) {
            $u['role_cible'] = role_cible((string)$u['role']);
            $u['deja_present'] = in_array((string)$u['identifiant'], $existants, true);
            $comptes[] = $u;
        }
    } catch (PDOException $e) {
        $erreur = "Lecture impossible : " . $e->getMessage()
                . " — vérifiez que l'utilisateur MySQL de TraçaBoucher a bien accès à la base " . DB_NAME_CAUSSELOT . ".";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $configure && !$erreur) {
    $choisis = array_map('intval', (array)($_POST['comptes'] ?? []));
    $copies = 0; $ignores = 0; $echecs = [];

    $insert = $pdo->prepare("INSERT INTO $table_partagee (identifiant, nom, email, mot_de_passe, role, actif)
                             VALUES (?, ?, ?, ?, ?, ?)");

    foreach ($comptes as $u) {
        if (!in_array((int)$u['id'], $choisis, true)) { continue; }
        if ($u['deja_present']) { $ignores++; continue; }
        try {
            // La table causselot attend un e-mail : à défaut, l'identifiant
            // fait l'affaire, il est unique et l'application n'y envoie rien.
            $insert->execute([
                $u['identifiant'], $u['nom'], $u['identifiant'],
                $u['mot_de_passe'], $u['role_cible'], (int)$u['actif'],
            ]);
            $copies++;
        } catch (PDOException $e) {
            $echecs[] = $u['identifiant'] . ' : ' . $e->getMessage();
        }
    }
    $rapport = ['copies' => $copies, 'ignores' => $ignores, 'echecs' => $echecs];

    // Recalcul pour que le tableau affiché reflète l'état réel.
    $existants = $pdo->query("SELECT identifiant FROM $table_partagee")->fetchAll(PDO::FETCH_COLUMN);
    $existants = array_map('strval', $existants);
    foreach ($comptes as $i => $u) {
        $comptes[$i]['deja_present'] = in_array((string)$u['identifiant'], $existants, true);
    }
}

$a_reprendre = array_filter($comptes, fn($u) => !$u['deja_present']);

require __DIR__ . '/includes/header.php';
?>

<h2 class="font-headline-lg text-2xl font-bold text-primary mb-1">Reprise des comptes</h2>
<p class="text-sm text-on-surface-variant mb-6">
  Recopie dans la base partagée CAUSSELOT les comptes qui n'existaient que dans TraçaBoucher,
  afin que leurs titulaires puissent continuer à se connecter. Les mots de passe sont conservés.
</p>

<?php if (!$configure): ?>
<div class="bg-error-container text-on-error-container rounded-xl p-5 text-sm">
  <p class="font-bold mb-1">Connexion unique pas encore configurée</p>
  <p>
    <code>DB_NAME_CAUSSELOT</code> doit désigner la base de <code>app.causselot.fr</code>
    dans <code>config.local.php</code>, et l'utilisateur MySQL de TraçaBoucher doit y avoir accès
    (hPanel &gt; Bases de données MySQL). Tant que ce n'est pas fait, il n'y a rien à reprendre.
  </p>
</div>

<?php elseif ($erreur): ?>
<div class="bg-error-container text-on-error-container rounded-xl p-5 text-sm"><?= h($erreur) ?></div>

<?php else: ?>

<?php if ($rapport): ?>
<div class="bg-primary-container text-on-primary-container rounded-xl p-4 mb-4 text-sm">
  <?= (int)$rapport['copies'] ?> compte(s) repris<?= $rapport['ignores'] ? ', ' . (int)$rapport['ignores'] . ' déjà présent(s)' : '' ?>.
</div>
<?php if ($rapport['echecs']): ?>
<div class="bg-error-container text-on-error-container rounded-xl p-4 mb-4 text-sm">
  <p class="font-bold mb-1">Échecs</p>
  <ul class="list-disc pl-5"><?php foreach ($rapport['echecs'] as $e): ?><li><?= h($e) ?></li><?php endforeach ?></ul>
</div>
<?php endif ?>
<?php endif ?>

<?php if (!$comptes): ?>
<div class="bg-surface rounded-xl border border-outline-variant p-6 text-sm text-on-surface-variant">
  Aucun compte local à reprendre.
</div>
<?php else: ?>
<form method="post">
  <div class="bg-surface rounded-xl border border-outline-variant p-5 mb-4">
    <div class="flex flex-col gap-2">
      <?php foreach ($comptes as $u): ?>
      <label class="flex items-center gap-3 py-2 border-b border-outline-variant last:border-0 <?= $u['deja_present'] ? 'opacity-50' : '' ?>">
        <input type="checkbox" name="comptes[]" value="<?= (int)$u['id'] ?>" class="w-5 h-5 rounded"
               <?= $u['deja_present'] ? 'disabled' : 'checked' ?>>
        <div class="flex-1 min-w-0">
          <div class="text-sm font-semibold"><?= h($u['nom']) ?></div>
          <div class="text-xs text-on-surface-variant">
            <?= h($u['identifiant']) ?> ·
            <?= h($u['role']) ?> &rarr; <strong><?= h($u['role_cible']) ?></strong>
            <?= $u['actif'] ? '' : ' · désactivé' ?>
          </div>
        </div>
        <span class="text-xs font-bold shrink-0 <?= $u['deja_present'] ? 'text-on-surface-variant' : 'text-primary' ?>">
          <?= $u['deja_present'] ? 'déjà présent' : 'à reprendre' ?>
        </span>
      </label>
      <?php endforeach ?>
    </div>
  </div>

  <?php if ($a_reprendre): ?>
  <button type="submit" class="bg-primary text-on-primary rounded-full px-6 py-3 font-bold text-sm">
    Reprendre les comptes cochés
  </button>
  <?php else: ?>
  <p class="text-sm text-primary font-semibold">Tous les comptes locaux sont déjà présents dans la base partagée.</p>
  <?php endif ?>
</form>

<p class="text-xs text-on-surface-variant mt-6">
  La table locale n'est pas modifiée : elle reste en place, simplement inutilisée pour la connexion.
  Les comptes se gèrent désormais depuis app.causselot.fr.
</p>
<?php endif ?>
<?php endif ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

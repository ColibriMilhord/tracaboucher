<?php
$page_active = 'utilisateurs';
$page_title  = 'Utilisateurs';
require_once __DIR__ . '/includes/auth.php';
$moi = exiger_admin();

$pdo = db();
$msg = '';

// ── Création / modification d'un compte
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $uid   = (int)($_POST['user_id'] ?? 0);
    $ident = trim($_POST['identifiant'] ?? '');
    $nom   = trim($_POST['nom'] ?? '');
    $role  = ($_POST['role'] ?? 'operateur') === 'admin' ? 'admin' : 'operateur';
    $actif = isset($_POST['actif']) ? 1 : 0;
    $mdp   = (string)($_POST['mot_de_passe'] ?? '');

    if ($ident === '' || $nom === '') {
        $msg = "L'identifiant et le nom sont obligatoires.";
    } elseif (!$uid && $mdp === '') {
        $msg = 'Un mot de passe est requis pour créer un compte.';
    } elseif ($mdp !== '' && ($e = verifier_force_mdp($mdp))) {
        $msg = $e;
    } elseif ($uid === (int)$moi['id'] && ($role !== 'admin' || !$actif)) {
        $msg = 'Vous ne pouvez pas retirer vos propres droits ni désactiver votre compte.';
    } else {
        try {
            if ($uid) {
                $pdo->prepare('UPDATE utilisateurs SET identifiant=?, nom=?, role=?, actif=? WHERE id=?')
                    ->execute([$ident, $nom, $role, $actif, $uid]);
                if ($mdp !== '') {
                    $pdo->prepare('UPDATE utilisateurs SET mot_de_passe=? WHERE id=?')
                        ->execute([password_hash($mdp, PASSWORD_DEFAULT), $uid]);
                }
            } else {
                $pdo->prepare('INSERT INTO utilisateurs (identifiant, nom, mot_de_passe, role, actif) VALUES (?,?,?,?,?)')
                    ->execute([$ident, $nom, password_hash($mdp, PASSWORD_DEFAULT), $role, $actif]);
            }
            header('Location: utilisateurs.php?msg=ok');
            exit;
        } catch (PDOException $e) {
            $msg = strpos($e->getMessage(), 'uk_identifiant') !== false
                 ? 'Cet identifiant est déjà utilisé.'
                 : 'Erreur : ' . $e->getMessage();
        }
    }
}

$ok = ($_GET['msg'] ?? '') === 'ok' ? 'Compte enregistré.' : '';
$users = $pdo->query('SELECT * FROM utilisateurs ORDER BY actif DESC, nom')->fetchAll();

$edit = null;
if (($_GET['action'] ?? '') === 'modifier' && ($eid = (int)($_GET['id'] ?? 0))) {
    $q = $pdo->prepare('SELECT * FROM utilisateurs WHERE id=?');
    $q->execute([$eid]);
    $edit = $q->fetch() ?: null;
}

require __DIR__ . '/includes/header.php';
?>

<h2 class="font-headline-lg text-2xl font-bold text-primary mb-1">Utilisateurs</h2>
<p class="text-sm text-on-surface-variant mb-6">Chaque saisie est horodatée et signée du nom de l'opérateur connecté.</p>

<?php if ($msg): ?><div class="bg-error-container text-on-error-container rounded-xl px-4 py-3 mb-4 text-sm"><?= h($msg) ?></div><?php endif ?>
<?php if ($ok):  ?><div class="bg-primary-container text-on-primary-container rounded-xl px-4 py-3 mb-4 text-sm"><?= h($ok) ?></div><?php endif ?>

<div class="bg-surface rounded-xl border border-outline-variant p-5 mb-6">
  <div class="flex flex-col gap-2">
    <?php foreach ($users as $u): ?>
    <div class="flex items-center gap-3 py-2 border-b border-outline-variant last:border-0 <?= $u['actif'] ? '' : 'opacity-40' ?>">
      <span class="material-symbols-outlined text-on-surface-variant"><?= $u['role'] === 'admin' ? 'shield_person' : 'person' ?></span>
      <div class="flex-1 min-w-0">
        <div class="text-sm font-semibold truncate"><?= h($u['nom']) ?><?= (int)$u['id'] === (int)$moi['id'] ? ' (vous)' : '' ?></div>
        <div class="text-xs text-on-surface-variant">
          <?= h($u['identifiant']) ?> · <?= $u['role'] === 'admin' ? 'administrateur' : 'opérateur' ?>
          <?= $u['actif'] ? '' : ' · désactivé' ?>
          <?= $u['derniere_connexion'] ? ' · dernière connexion ' . date('d/m/Y H:i', strtotime($u['derniere_connexion'])) : '' ?>
        </div>
      </div>
      <a href="utilisateurs.php?action=modifier&id=<?= (int)$u['id'] ?>" class="material-symbols-outlined text-primary p-2 rounded-full hover:bg-surface-container">edit</a>
    </div>
    <?php endforeach ?>
  </div>
</div>

<div class="bg-surface rounded-xl border border-outline-variant p-5">
  <h3 class="font-headline-md font-bold mb-4"><?= $edit ? 'Modifier « ' . h($edit['nom']) . ' »' : 'Ajouter un compte' ?></h3>
  <form method="post" autocomplete="off" class="grid sm:grid-cols-2 gap-4">
    <input type="hidden" name="user_id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <div>
      <label class="block text-sm font-semibold mb-1">Nom complet</label>
      <input type="text" name="nom" required value="<?= h($edit['nom'] ?? '') ?>" class="w-full rounded-xl border-outline-variant">
    </div>
    <div>
      <label class="block text-sm font-semibold mb-1">Identifiant</label>
      <input type="text" name="identifiant" required value="<?= h($edit['identifiant'] ?? '') ?>" class="w-full rounded-xl border-outline-variant">
    </div>
    <div>
      <label class="block text-sm font-semibold mb-1">Mot de passe<?= $edit ? ' <span class="text-xs font-normal text-on-surface-variant">(laisser vide pour ne pas changer)</span>' : '' ?></label>
      <input type="password" name="mot_de_passe" autocomplete="new-password" <?= $edit ? '' : 'required' ?> class="w-full rounded-xl border-outline-variant">
      <p class="text-xs text-on-surface-variant mt-1">10 caractères minimum, une lettre et un chiffre.</p>
    </div>
    <div>
      <label class="block text-sm font-semibold mb-1">Rôle</label>
      <select name="role" class="w-full rounded-xl border-outline-variant">
        <option value="operateur" <?= ($edit['role'] ?? '') === 'operateur' ? 'selected' : '' ?>>Opérateur — saisie des lots</option>
        <option value="admin"     <?= ($edit['role'] ?? '') === 'admin'     ? 'selected' : '' ?>>Administrateur — + réglages et comptes</option>
      </select>
    </div>
    <label class="sm:col-span-2 flex items-center gap-2 text-sm">
      <input type="checkbox" name="actif" value="1" <?= (!$edit || $edit['actif']) ? 'checked' : '' ?> class="rounded">
      Compte actif
    </label>
    <div class="sm:col-span-2 flex gap-2">
      <button name="save_user" value="1" class="bg-primary text-on-primary rounded-full px-6 py-3 font-bold text-sm"><?= $edit ? 'Enregistrer' : 'Ajouter' ?></button>
      <?php if ($edit): ?><a href="utilisateurs.php" class="bg-surface-container-high rounded-full px-6 py-3 font-bold text-sm">Annuler</a><?php endif ?>
    </div>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

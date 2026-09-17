<?php
require_once __DIR__ . '/includes/auth.php';
session_demarrer();

// Premier démarrage : aucun compte en base → on force la création de l'administrateur
$premier = aucun_compte();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $premier && isset($_POST['creer_admin'])) {
    $ident = trim($_POST['identifiant'] ?? '');
    $nom   = trim($_POST['nom'] ?? '');
    $mdp   = (string)($_POST['mot_de_passe'] ?? '');
    $mdp2  = (string)($_POST['mot_de_passe2'] ?? '');

    if ($ident === '' || $nom === '') {
        $msg = "L'identifiant et le nom sont obligatoires.";
    } elseif ($mdp !== $mdp2) {
        $msg = 'Les deux mots de passe ne correspondent pas.';
    } elseif ($e = verifier_force_mdp($mdp)) {
        $msg = $e;
    } else {
        // email est repris de l'identifiant : la colonne est historiquement
        // obligatoire côté app.causselot.fr (ancien schéma partagé).
        db()->prepare('INSERT INTO ' . DB_NAME_CAUSSELOT . '.utilisateurs (identifiant, nom, email, mot_de_passe, role) VALUES (?,?,?,?,?)')
            ->execute([$ident, $nom, $ident, password_hash($mdp, PASSWORD_DEFAULT), 'admin']);
        connecter($ident, $mdp);
        header('Location: index.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$premier && isset($_POST['connexion'])) {
    $_SESSION['essais'] = ($_SESSION['essais'] ?? 0) + 1;
    if ($_SESSION['essais'] > 8) {
        $msg = 'Trop de tentatives. Patientez une minute avant de réessayer.';
        if (time() - ($_SESSION['essais_depuis'] ?? 0) > 60) { $_SESSION['essais'] = 1; $msg = ''; }
    }
    if ($msg === '') {
        $_SESSION['essais_depuis'] = $_SESSION['essais_depuis'] ?? time();
        $u = connecter(trim($_POST['identifiant'] ?? ''), (string)($_POST['mot_de_passe'] ?? ''));
        if ($u) {
            unset($_SESSION['essais'], $_SESSION['essais_depuis']);
            $suite = $_POST['suite'] ?? 'index.php';
            // On n'accepte qu'une redirection interne
            if (!preg_match('#^/?[A-Za-z0-9_./?=&-]*$#', $suite) || strpos($suite, '//') !== false) $suite = 'index.php';
            header('Location: ' . $suite);
            exit;
        }
        $msg = 'Identifiant ou mot de passe incorrect.';
        usleep(400000);
    }
}

if (!$premier && utilisateur_courant()) { header('Location: index.php'); exit; }
$suite = $_GET['suite'] ?? 'index.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Connexion – <?= h(APP_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Chivo:wght@700;800&family=Public+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:"Public Sans",-apple-system,sans-serif;background:#f9f8f4;min-height:100dvh;
       display:flex;align-items:center;justify-content:center;padding:20px;color:#2c2c2c}
  .box{background:#fff;border:1px solid #e0e0e0;border-radius:16px;padding:32px;max-width:400px;width:100%}
  h1{font-family:Chivo,sans-serif;font-size:22px;color:#2c5530;margin-bottom:4px}
  .sub{font-size:13px;color:#4a4a4a;margin-bottom:24px}
  label{display:block;font-size:13px;font-weight:600;margin-bottom:6px}
  input{width:100%;padding:12px 14px;font-size:16px;border:1px solid #e0e0e0;border-radius:12px;margin-bottom:16px}
  input:focus{outline:2px solid #2c5530;border-color:#2c5530}
  button{width:100%;padding:15px;border:none;border-radius:999px;background:#2c5530;color:#fff;
         font-size:16px;font-weight:700;cursor:pointer;font-family:inherit}
  .err{background:#ffdad6;color:#93000a;padding:12px 14px;border-radius:10px;font-size:13px;margin-bottom:16px}
  .info{background:#e7f3ea;color:#1b5e20;padding:12px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;line-height:1.5}
  .hint{font-size:12px;color:#4a4a4a;margin:-10px 0 16px}
</style>
</head>
<body>
<div class="box">
  <h1><?= h(APP_NAME) ?></h1>
  <div class="sub"><?= $premier ? 'Première connexion — créez le compte administrateur' : 'Traçabilité de l\'atelier' ?></div>

  <?php if ($msg): ?><div class="err"><?= h($msg) ?></div><?php endif ?>

  <?php if ($premier): ?>
  <div class="info">Aucun compte n'existe encore. Ce premier compte sera administrateur : il pourra créer les comptes des opérateurs.</div>
  <form method="post" autocomplete="off">
    <label for="nom">Nom complet</label>
    <input id="nom" name="nom" required value="<?= h($_POST['nom'] ?? '') ?>" placeholder="ex : Marie Dupont">

    <label for="identifiant">Identifiant de connexion</label>
    <input id="identifiant" name="identifiant" required value="<?= h($_POST['identifiant'] ?? '') ?>" placeholder="ex : marie">

    <label for="mdp">Mot de passe</label>
    <input id="mdp" name="mot_de_passe" type="password" required autocomplete="new-password">
    <p class="hint">10 caractères minimum, avec au moins une lettre et un chiffre.</p>

    <label for="mdp2">Confirmer le mot de passe</label>
    <input id="mdp2" name="mot_de_passe2" type="password" required autocomplete="new-password">

    <button name="creer_admin" value="1">Créer le compte</button>
  </form>

  <?php else: ?>
  <form method="post" autocomplete="on">
    <input type="hidden" name="suite" value="<?= h($suite) ?>">
    <label for="identifiant">Identifiant</label>
    <input id="identifiant" name="identifiant" required autofocus autocomplete="username">

    <label for="mdp">Mot de passe</label>
    <input id="mdp" name="mot_de_passe" type="password" required autocomplete="current-password">

    <button name="connexion" value="1">Se connecter</button>
  </form>
  <?php endif ?>
</div>
</body>
</html>

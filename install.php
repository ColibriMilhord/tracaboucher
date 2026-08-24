<?php
// ============================================================
//  TraçaBoucher – Installateur automatique
//  Ouvrir une seule fois via navigateur, puis SUPPRIMER ce fichier
// ============================================================
$step = (int)($_GET['step'] ?? 0);
$errors = [];

require_once __DIR__ . '/config.php';

if ($step === 1) {
    // Lecture du fichier SQL
    $sql = file_get_contents(__DIR__ . '/install.sql');
    if (!$sql) { $errors[] = 'Impossible de lire install.sql'; }
    else {
        try {
            $pdo = new PDO(
                'mysql:host='.DB_HOST.';charset=utf8mb4', DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
            );
            // Créer la base si elle n'existe pas
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.DB_NAME.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec('USE `'.DB_NAME.'`');

            // Exécuter statement par statement
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                fn($s) => !empty($s) && substr($s, 0, 2) !== '--'
            );
            $ok = 0;
            foreach ($statements as $stmt) {
                if (trim($stmt)) { $pdo->exec($stmt); $ok++; }
            }
            // Créer dossiers uploads
            foreach (['uploads','uploads/abattoir','uploads/factures'] as $d) {
                $path = __DIR__.'/'.$d;
                if (!is_dir($path)) mkdir($path, 0755, true);
            }
            // Fichier .htaccess pour uploads
            file_put_contents(__DIR__.'/uploads/.htaccess',
                "Options -Indexes\nAddType application/octet-stream .sql\n");

            $success = $ok . ' instructions SQL exécutées avec succès.';
        } catch (PDOException $e) {
            $errors[] = $e->getMessage();
        }
    }
}

// Création dossiers au cas où
foreach (['uploads','uploads/abattoir','uploads/factures'] as $d) {
    $path = __DIR__.'/'.$d;
    if (!is_dir($path)) @mkdir($path, 0755, true);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Installation – TraçaBoucher</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:-apple-system,sans-serif;background:#F5F3F0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
    .box{background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,.12);padding:32px;max-width:480px;width:100%}
    h1{font-size:22px;font-weight:900;color:#7A1C1C;margin-bottom:6px}
    .sub{color:#6B7280;font-size:13px;margin-bottom:24px}
    .step-card{border:2px solid #E8E4E4;border-radius:12px;padding:16px;margin-bottom:14px}
    .step-label{font-weight:800;font-size:14px;margin-bottom:4px}
    .step-val{font-size:13px;color:#6B7280;font-family:monospace}
    .btn{display:block;width:100%;padding:15px;border-radius:12px;border:none;font-size:16px;font-weight:800;cursor:pointer;text-align:center;text-decoration:none;margin-top:16px}
    .btn-rouge{background:#7A1C1C;color:#fff}
    .btn-ok{background:#1A6B3C;color:#fff}
    .alert{padding:12px 14px;border-radius:10px;font-size:13px;margin-bottom:14px}
    .alert-err{background:#fdf0f0;color:#7A1C1C;border-left:4px solid #7A1C1C}
    .alert-ok {background:#edfaf1;color:#155724;border-left:4px solid #28a745}
    code{background:#F5F3F0;padding:2px 6px;border-radius:4px;font-size:12px}
  </style>
</head>
<body>
<div class="box">
  <h1>🥩 TraçaBoucher</h1>
  <div class="sub">Installateur automatique v1.0</div>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-err">
    <strong>❌ Erreur :</strong><br>
    <?php foreach ($errors as $e): ?><?= htmlspecialchars($e) ?><br><?php endforeach ?>
  </div>
  <?php elseif (isset($success)): ?>
  <div class="alert alert-ok">✅ <?= htmlspecialchars($success) ?></div>
  <div class="alert alert-ok">📁 Dossiers uploads créés avec succès.</div>
  <div style="background:#FEF9ED;border:1px solid #E8A020;border-radius:10px;padding:14px;font-size:13px;margin-bottom:14px">
    ⚠️ <strong>IMPORTANT</strong> : Supprimez le fichier <code>install.php</code> de votre serveur pour des raisons de sécurité.
  </div>
  <a href="index.php" class="btn btn-ok">🚀 Accéder à l'application</a>
  <?php else: ?>

  <!-- Vérifications -->
  <div class="step-card">
    <div class="step-label">Base de données</div>
    <div class="step-val">Host: <?= htmlspecialchars(DB_HOST) ?> · DB: <?= htmlspecialchars(DB_NAME) ?></div>
    <div class="step-val">User: <?= htmlspecialchars(DB_USER) ?></div>
  </div>

  <div class="step-card">
    <div class="step-label">Dossiers</div>
    <?php foreach (['uploads','uploads/abattoir','uploads/factures'] as $d): ?>
    <div class="step-val">
      <?= is_dir(__DIR__.'/'.$d)?'✅':'❌' ?> /<?= $d ?>
      <?= is_writable(__DIR__.'/'.$d)?'(accessible)':'(⚠️ non accessible)' ?>
    </div>
    <?php endforeach ?>
  </div>

  <div class="step-card">
    <div class="step-label">PHP & Extensions</div>
    <div class="step-val">✅ PHP <?= PHP_VERSION ?></div>
    <div class="step-val"><?= extension_loaded('pdo_mysql')?'✅':'❌' ?> PDO MySQL</div>
    <div class="step-val"><?= extension_loaded('fileinfo')?'✅':'⚠️' ?> FileInfo</div>
    <div class="step-val"><?= function_exists('move_uploaded_file')?'✅':'❌' ?> Upload fichiers</div>
  </div>

  <div style="font-size:13px;color:#6B7280;margin-bottom:6px">
    Cliquer sur le bouton pour créer la base de données et toutes les tables.
  </div>
  <a href="install.php?step=1" class="btn btn-rouge">⚡ Lancer l'installation</a>
  <?php endif ?>
</div>
</body></html>

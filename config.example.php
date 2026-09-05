<?php
// ============================================================
//  Modèle de configuration — copier en config.php sur le serveur
//  et y reporter les identifiants de hPanel > Bases MySQL.
//  config.php est volontairement exclu du dépôt.
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'u000000000_xxxxx');
define('DB_USER', 'u000000000_xxxxx');
define('DB_PASS', '');

define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', 'uploads/');

define('APP_NAME',    'TraçaBoucher');
define('APP_VERSION', '2.0');

define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);
define('ALLOWED_EXT', ['jpg','jpeg','png','pdf','webp','heic']);

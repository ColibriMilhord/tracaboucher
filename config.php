<?php
// ============================================================
//  TraçaBoucher – Configuration
//
//  Ce fichier est versionné et NE CONTIENT AUCUN IDENTIFIANT.
//  Les vrais identifiants vivent dans config.local.php, exclu du dépôt :
//  un déploiement git ne peut donc ni les écraser ni les supprimer.
//  Voir config.example.php pour le modèle à recopier.
// ============================================================

if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

// Appliquées uniquement si config.local.php ne les a pas déjà définies.
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_NAME') || define('DB_NAME', '');
defined('DB_USER') || define('DB_USER', '');
defined('DB_PASS') || define('DB_PASS', '');

defined('UPLOAD_DIR') || define('UPLOAD_DIR', __DIR__ . '/uploads/');
defined('UPLOAD_URL') || define('UPLOAD_URL', 'uploads/');

defined('APP_NAME')    || define('APP_NAME', 'TraçaBoucher');
defined('APP_VERSION') || define('APP_VERSION', '2.0');

defined('MAX_UPLOAD_SIZE') || define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);
defined('ALLOWED_EXT')     || define('ALLOWED_EXT', ['jpg','jpeg','png','pdf','webp','heic']);

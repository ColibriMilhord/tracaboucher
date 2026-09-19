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

// Base partagée des comptes (connexion unique avec app.causselot.fr).
// Par défaut identique à DB_NAME (comportement inchangé tant que
// config.local.php ne définit pas explicitement la base causselot).
defined('DB_NAME_CAUSSELOT') || define('DB_NAME_CAUSSELOT', DB_NAME);
defined('CAUSSELOT_URL') || define('CAUSSELOT_URL', '');

defined('UPLOAD_DIR') || define('UPLOAD_DIR', __DIR__ . '/uploads/');
defined('UPLOAD_URL') || define('UPLOAD_URL', 'uploads/');

defined('APP_NAME') || define('APP_NAME', 'TraçaBoucher');
// La version vit dans version.php : elle décrit le code livré, pas le
// serveur, et ne doit pas pouvoir être écrasée par config.local.php.

defined('MAX_UPLOAD_SIZE') || define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);
defined('ALLOWED_EXT')     || define('ALLOWED_EXT', ['jpg','jpeg','png','pdf','webp','heic']);

<?php
// ============================================================
//  TraçaBoucher – Configuration
//  À adapter selon votre hébergement Hostinger
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'tracabilite');   // Votre nom de base Hostinger
define('DB_USER', 'root');          // Votre user MySQL Hostinger
define('DB_PASS', 'changeme');              // Votre mot de passe MySQL

define('UPLOAD_DIR',     __DIR__ . '/uploads/');
define('UPLOAD_DIR_ABA', __DIR__ . '/uploads/abattoir/');
define('UPLOAD_DIR_FAC', __DIR__ . '/uploads/factures/');
define('UPLOAD_URL',     'uploads/');

define('APP_NAME',    'TraçaBoucher');
define('APP_VERSION', '1.0');

// Taille max upload (en octets) : 10 Mo
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);

// Extensions autorisées pour les scans
define('ALLOWED_EXT', ['jpg','jpeg','png','pdf','webp']);

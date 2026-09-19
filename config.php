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

// Une erreur PHP affichée à l'écran livre au visiteur le nom de
// l'utilisateur MySQL, celui des bases et le chemin du serveur : on la
// journalise sans jamais la montrer. Passer APP_DEBUG à true dans
// config.local.php pour la revoir le temps d'une mise au point.
defined('APP_DEBUG') || define('APP_DEBUG', false);
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

// Appliquées uniquement si config.local.php ne les a pas déjà définies.
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_NAME') || define('DB_NAME', '');
defined('DB_USER') || define('DB_USER', '');
defined('DB_PASS') || define('DB_PASS', '');

// Base partagée des comptes (connexion unique avec app.causselot.fr).
// Par défaut identique à DB_NAME (comportement inchangé tant que
// config.local.php ne définit pas explicitement la base causselot).
defined('DB_NAME_CAUSSELOT') || define('DB_NAME_CAUSSELOT', DB_NAME);

// Identifiants propres à la base du portail. Quand ils sont fournis,
// TraçaBoucher ouvre une seconde connexion pour lire les comptes, au
// lieu de nommer l'autre base dans ses requêtes : chaque base garde son
// utilisateur MySQL et aucun droit inter-bases n'est à demander.
defined('DB_USER_CAUSSELOT') || define('DB_USER_CAUSSELOT', '');
defined('DB_PASS_CAUSSELOT') || define('DB_PASS_CAUSSELOT', '');
defined('DB_HOST_CAUSSELOT') || define('DB_HOST_CAUSSELOT', DB_HOST);

defined('CAUSSELOT_URL') || define('CAUSSELOT_URL', '');

defined('UPLOAD_DIR') || define('UPLOAD_DIR', __DIR__ . '/uploads/');
defined('UPLOAD_URL') || define('UPLOAD_URL', 'uploads/');

defined('APP_NAME') || define('APP_NAME', 'TraçaBoucher');
// La version vit dans version.php : elle décrit le code livré, pas le
// serveur, et ne doit pas pouvoir être écrasée par config.local.php.

defined('MAX_UPLOAD_SIZE') || define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);
defined('ALLOWED_EXT')     || define('ALLOWED_EXT', ['jpg','jpeg','png','pdf','webp','heic']);

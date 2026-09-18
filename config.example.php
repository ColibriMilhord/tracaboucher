<?php
// ============================================================
//  Modèle à recopier en config.local.php SUR LE SERVEUR.
//  Valeurs à prendre dans hPanel > Bases de données MySQL.
//  config.local.php est exclu du dépôt : il survit aux déploiements.
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'u000000000_xxxxx');
define('DB_USER', 'u000000000_xxxxx');
define('DB_PASS', 'votre_mot_de_passe');

// Connexion unique (SSO) avec app.causselot.fr : nom de la base utilisée
// par ce site (définie dans son propre config.local.php). L'utilisateur
// MySQL ci-dessus doit avoir reçu l'accès à cette base dans hPanel >
// Bases de données MySQL. Voir docs/integration-tracabilite.md dans le
// dépôt appcausselot pour la procédure complète.
define('DB_NAME_CAUSSELOT', 'u000000000_causselot');

// Portail CAUSSELOT (gestion des comptes centralisée là-bas désormais).
define('CAUSSELOT_URL', 'https://app.causselot.fr/');

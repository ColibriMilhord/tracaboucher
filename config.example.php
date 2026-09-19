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

// Connexion unique (SSO) avec app.causselot.fr.
//
// ATTENTION : c'est la base D'APP.CAUSSELOT.FR qu'il faut nommer ici,
// pas celle de TraçaBoucher. Sa valeur exacte se lit dans le
// config.local.php d'app.causselot.fr, ligne DB_NAME. Y mettre la base
// de TraçaBoucher, ou une base à laquelle DB_USER n'a pas accès, coupe
// la connexion unique : l'application se rabat alors sur ses comptes
// locaux et le signale dans Paramètres.
//
// L'utilisateur MySQL ci-dessus doit en outre avoir reçu l'accès à
// cette base dans hPanel > Bases de données MySQL. Voir
// docs/integration-tracabilite.md dans le dépôt appcausselot.
define('DB_NAME_CAUSSELOT', 'u000000000_causselot');

// Portail CAUSSELOT (gestion des comptes centralisée là-bas désormais).
define('CAUSSELOT_URL', 'https://app.causselot.fr/');

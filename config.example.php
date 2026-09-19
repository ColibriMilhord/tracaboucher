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
define('DB_NAME_CAUSSELOT', 'u000000000_causselot');

// Identifiants MySQL de CETTE base, recopiés depuis le config.local.php
// d'app.causselot.fr (ses lignes DB_USER et DB_PASS). C'est la façon la
// plus simple de partager les comptes sur un hébergement mutualisé :
// TraçaBoucher ouvre une seconde connexion, chaque base garde son propre
// utilisateur, et il n'y a aucun droit inter-bases à demander.
define('DB_USER_CAUSSELOT', 'u000000000_causselot');
define('DB_PASS_CAUSSELOT', 'le_mot_de_passe_de_app_causselot');

// Variante sans ces deux lignes : l'utilisateur MySQL de TraçaBoucher
// doit alors être associé AUSSI à la base ci-dessus, dans hPanel >
// Bases de données MySQL. Voir docs/integration-tracabilite.md dans le
// dépôt appcausselot.

// Portail CAUSSELOT (gestion des comptes centralisée là-bas désormais).
define('CAUSSELOT_URL', 'https://app.causselot.fr/');

// Ouvre diagnostic.php, qui liste les bases réellement accessibles à
// DB_USER et indique quoi écrire ci-dessus. À retirer une fois réglé.
// define('DIAGNOSTIC_CLE', 'un-mot-long-et-unique');

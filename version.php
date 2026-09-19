<?php
// ============================================================
//  Version de l'application.
//
//  À incrémenter dans le même commit que toute modification visible :
//  c'est ce qui permet de vérifier d'un coup d'œil, après un
//  déploiement, que le serveur fait bien tourner la dernière version
//  (et non un déploiement resté en arrière, ou une copie de fichiers
//  figée lors d'un déménagement de domaine).
//
//  Convention : MAJEURE.MINEURE.CORRECTIF
//    - MINEURE   : nouvelle fonctionnalité ou refonte d'un écran
//    - CORRECTIF : correction de bug, ajustement d'ergonomie
//
//  Des fonctions plutôt que des constantes : une constante du même nom
//  restée dans config.local.php prendrait la main et afficherait
//  éternellement l'ancienne version — exactement ce que ce fichier est
//  censé empêcher.
// ============================================================

function version_app(): string  { return '2.4.0'; }
function version_date(): string { return '2026-09-19'; }

// Ex. : « v2.1.0 · 19/09/2026 »
function version_affichee(): string {
    return 'v' . version_app() . ' · ' . date('d/m/Y', strtotime(version_date()));
}

// Appelé directement dans le navigateur (…/version.php), ce fichier
// répond la version en texte brut : c'est le moyen le plus court de
// savoir ce qu'un serveur exécute réellement, sans connexion et sans
// page mise en cache. Inclus par une autre page, il n'affiche rien.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    header('Content-Type: text/plain; charset=UTF-8');
    echo version_affichee(), "\n";
}

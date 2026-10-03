<?php
// ============================================================
//  Les numéros des lots ouverts, pour que l'écran Atelier sache
//  qu'un collègue vient d'en ouvrir un. Rien d'autre ne sort d'ici.
// ============================================================
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/atelier.php';
exiger_connexion();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$lots = [];
if (atelier_installe()) {
    $lots = db()->query("SELECT num_lot FROM lots_sortie WHERE statut = 'ouvert'
                          ORDER BY date_fabrication DESC, id DESC")->fetchAll(PDO::FETCH_COLUMN);
}
echo json_encode(['lots' => $lots], JSON_UNESCAPED_UNICODE);

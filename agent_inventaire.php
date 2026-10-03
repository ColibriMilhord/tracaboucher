<?php
// ============================================================
//  Inventaire de la base DFS, remonté par l'agent balance.
//
//  Uniquement des noms de tables et des comptages : de quoi savoir où
//  DFS range les pesées, sans copier la moindre donnée. Authentifié par
//  le même jeton que les exports.
// ============================================================
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/plain; charset=utf-8');

$jeton_attendu = reglage('token_export');
$jeton_fourni  = (string)($_POST['token'] ?? $_GET['token'] ?? '');
if ($jeton_attendu === '' || $jeton_fourni === '' || !hash_equals($jeton_attendu, $jeton_fourni)) {
    http_response_code(403);
    exit('jeton invalide');
}

// L'agent envoie « nom;lignes » par ligne — plus simple à produire en
// PowerShell qu'un JSON, et plus facile à relire en cas de souci.
$brut = (string)($_POST['tables'] ?? '');
$tables = [];
foreach (preg_split('/\r?\n/', $brut) as $ligne) {
    $ligne = trim($ligne);
    if ($ligne === '') continue;
    [$nom, $lignes] = array_pad(explode(';', $ligne, 2), 2, '0');
    $nom = preg_replace('/[^A-Za-z0-9_$]/', '', $nom);
    if ($nom === '') continue;
    $tables[] = ['nom' => mb_substr($nom, 0, 64), 'lignes' => (int)$lignes];
    if (count($tables) >= 500) break;    // une base DFS n'en a pas tant
}

db()->prepare('INSERT INTO reglages (cle, valeur) VALUES (?,?) ON DUPLICATE KEY UPDATE valeur=VALUES(valeur)')
    ->execute(['agent_inventaire', json_encode([
        'le'     => date('c'),
        'base'   => mb_substr(preg_replace('/[^A-Za-z0-9_$]/', '', (string)($_POST['base'] ?? '')), 0, 64),
        'tables' => $tables,
    ], JSON_UNESCAPED_UNICODE)]);

echo 'OK ' . count($tables);

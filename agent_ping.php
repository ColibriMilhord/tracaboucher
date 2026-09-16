<?php
// ============================================================
//  Réception de l'état de l'agent balance (pont PC atelier).
//  Authentifié par le même jeton que les exports. L'agent y POSTe
//  le résultat de sa dernière synchro ; l'Assistant balance l'affiche.
//  Aucune donnée sensible : juste un état ok/ko + message + horodatage.
// ============================================================
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/plain; charset=utf-8');

$jeton_attendu = reglage('token_export');
$jeton_fourni  = (string)($_POST['token'] ?? $_GET['token'] ?? '');
if ($jeton_attendu === '' || $jeton_fourni === '' || !hash_equals($jeton_attendu, $jeton_fourni)) {
    http_response_code(403);
    exit('jeton invalide');
}

$etat    = ($_POST['etat'] ?? '') === 'ok' ? 'ok' : 'ko';
$message = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 300);
$nb      = (int)($_POST['nb'] ?? 0);

$statut = json_encode([
    'etat'    => $etat,
    'message' => $message,
    'nb'      => $nb,
    'le'      => date('c'),
], JSON_UNESCAPED_UNICODE);

db()->prepare('INSERT INTO reglages (cle, valeur) VALUES (?,?) ON DUPLICATE KEY UPDATE valeur=VALUES(valeur)')
    ->execute(['agent_statut', $statut]);

echo 'OK';

<?php
// ============================================================
//  L'agent balance, prêt à installer : un zip à extraire sur le PC de
//  la balance, avec l'URL de récupération déjà dedans (url.txt), pour
//  qu'installer.bat n'ait plus rien à faire coller.
//
//  Réservé aux administrateurs : url.txt porte le jeton d'accès.
//  En POST seulement, puisqu'il crée ce jeton s'il n'existe pas encore.
// ============================================================
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/dfs.php';
$moi = exiger_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: guide.php');
    exit;
}
if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('L\'extension PHP « zip » manque sur ce serveur (hPanel > PHP > Extensions).');
}

$jeton = reglage('token_export');
if ($jeton === '') {
    $jeton = bin2hex(random_bytes(24));
    db()->prepare('INSERT INTO reglages (cle, valeur) VALUES (?,?) ON DUPLICATE KEY UPDATE valeur=VALUES(valeur)')
        ->execute(['token_export', $jeton]);
}

$fichiers = ['installer.bat', 'maj_dfs.bat', 'agent-balance.ps1', 'genconfig.ps1',
             'installer-tache.ps1', 'config.exemple.ps1', 'README.md'];
$tmp = tempnam(sys_get_temp_dir(), 'agent');
$zip = new ZipArchive();
$zip->open($tmp, ZipArchive::OVERWRITE);
foreach ($fichiers as $f) {
    $contenu = @file_get_contents(__DIR__ . '/agent-balance/' . $f);
    if ($contenu === false) continue;
    // cmd.exe perd ses étiquettes (goto) dans un .bat en fins de ligne LF.
    if (substr($f, -4) === '.bat') {
        $contenu = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $contenu));
    }
    $zip->addFromString('agent-balance/' . $f, $contenu);
}
$zip->addFromString('agent-balance/url.txt', url_recuperation($jeton) . "\r\n");
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="agent-balance.zip"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: no-store');
readfile($tmp);
unlink($tmp);

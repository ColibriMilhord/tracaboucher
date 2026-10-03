<?php
// ============================================================
//  Le dossier de l'agent, empaqueté à la volée.
//
//  Il est dans le dépôt, donc toujours à la version du serveur : pas de
//  ZIP à régénérer et à oublier de mettre à jour. La configuration
//  (jeton, chemins) n'y est PAS : elle est posée par installer.bat sur
//  le poste, et ce fichier ne doit jamais contenir de secret.
// ============================================================
require_once __DIR__ . '/includes/auth.php';
$moi = exiger_admin();

$dossier = __DIR__ . '/agent-balance';
if (!is_dir($dossier)) {
    http_response_code(404);
    exit("Le dossier de l'agent est introuvable sur ce serveur.");
}
if (!class_exists('ZipArchive')) {
    http_response_code(501);
    exit("L'extension ZIP n'est pas disponible sur ce serveur. "
       . "Récupérez le dossier agent-balance directement depuis le dépôt.");
}

$tmp = tempnam(sys_get_temp_dir(), 'agent');
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
    @unlink($tmp);
    http_response_code(500);
    exit("Création de l'archive impossible.");
}

foreach (scandir($dossier) ?: [] as $f) {
    if ($f === '.' || $f === '..') continue;
    $chemin = $dossier . '/' . $f;
    // Jamais la config réelle d'un poste : elle contient le jeton et le
    // mot de passe MySQL. Seul le modèle part.
    if (!is_file($chemin) || $f === 'config.ps1') continue;
    $zip->addFile($chemin, 'agent-balance/' . $f);
}
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="agent-balance.zip"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: no-store');
readfile($tmp);
@unlink($tmp);

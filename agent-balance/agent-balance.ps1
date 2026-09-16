# ============================================================
#  Agent balance TracaBoucher
#  Recupere les produits depuis l'application web et depose le fichier
#  la ou DFS (DGI/RGI) va le lire. Sauvegarde avant chaque operation.
#  NE MODIFIE JAMAIS directement la base de DFS : depot de fichier seulement.
#
#  Lancement manuel :  powershell -ExecutionPolicy Bypass -File agent-balance.ps1
#  Lancement planifie : voir installer-tache.ps1
# ============================================================
[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$racine = Split-Path -Parent $MyInvocation.MyCommand.Path

# --- Journal -------------------------------------------------
$logFile = Join-Path $racine 'agent-balance.log'
function Journal([string]$niveau, [string]$msg) {
    $ligne = ('{0}  [{1}]  {2}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $niveau, $msg)
    Add-Content -Path $logFile -Value $ligne -Encoding UTF8
    Write-Host $ligne
}

try {
    # --- Configuration ---------------------------------------
    $configFile = Join-Path $racine 'config.ps1'
    if (-not (Test-Path $configFile)) {
        throw "config.ps1 introuvable. Copiez config.exemple.ps1 en config.ps1 et renseignez-le."
    }
    . $configFile
    if (-not $Config -or -not $Config.Url -or $Config.Url -match 'COLLEZ_LE_JETON') {
        throw "config.ps1 incomplet : renseignez l'URL avec le jeton."
    }

    Journal 'INFO' 'Demarrage.'

    # TLS 1.2 pour Windows PowerShell 5.1
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

    # --- Dossiers --------------------------------------------
    $destDir = Split-Path -Parent $Config.Destination
    foreach ($d in @($destDir, $Config.Backups)) {
        if ($d -and -not (Test-Path $d)) { New-Item -ItemType Directory -Force -Path $d | Out-Null }
    }
    $horo = Get-Date -Format 'yyyyMMdd_HHmmss'

    # --- 1. Telechargement dans un fichier temporaire ---------
    $tmp = Join-Path $env:TEMP ("traca_articles_{0}.csv" -f $horo)
    Journal 'INFO' ("Telechargement depuis {0}" -f ($Config.Url -replace 'token=[^&]+', 'token=***'))
    Invoke-WebRequest -Uri $Config.Url -OutFile $tmp -UseBasicParsing -TimeoutSec 60

    # --- 2. Validation : c'est bien notre export, pas une page d'erreur ---
    if (-not (Test-Path $tmp) -or (Get-Item $tmp).Length -eq 0) {
        throw "Fichier telecharge vide."
    }
    $premiere = (Get-Content -Path $tmp -TotalCount 1 -Encoding UTF8) -replace "\xEF\xBB\xBF", ''
    if ($premiere -notmatch 'IdArticulo' -or $premiere -notmatch 'EANScanner') {
        throw "Contenu inattendu (pas l'en-tete dat_articulo). Jeton invalide ou URL erronee ? Fichier NON remplace."
    }
    $nbLignes = (Get-Content -Path $tmp -Encoding UTF8 | Measure-Object -Line).Lines
    Journal 'INFO' ("Fichier valide : {0} ligne(s)." -f $nbLignes)

    # --- 3. Sauvegarde de l'ancien fichier de destination -----
    if (Test-Path $Config.Destination) {
        $bkFichier = Join-Path $Config.Backups ("articles_{0}.csv" -f $horo)
        Copy-Item -Path $Config.Destination -Destination $bkFichier -Force
        Journal 'INFO' ("Ancien fichier sauvegarde : {0}" -f $bkFichier)
    }

    # --- 4. Sauvegarde (dump) de la base DFS, en lecture seule ----
    if ($Config.DumpAvant -and $Config.MysqlDump -and (Test-Path $Config.MysqlDump)) {
        $dump = Join-Path $Config.Backups ("sys_datos_dfs_{0}.sql" -f $horo)
        Journal 'INFO' 'Sauvegarde de la base DFS (mysqldump, lecture seule).'
        $args = @(
            "--host=$($Config.DbHost)", "--port=$($Config.DbPort)",
            "--user=$($Config.DbUser)", "--password=$($Config.DbPass)",
            '--single-transaction', '--default-character-set=utf8', $Config.DbName
        )
        & $Config.MysqlDump @args | Out-File -FilePath $dump -Encoding UTF8
        if ($LASTEXITCODE -ne 0) {
            Journal 'ATTENTION' "mysqldump a renvoye le code $LASTEXITCODE. Sauvegarde base ignoree, on continue."
        } else {
            Journal 'INFO' ("Base sauvegardee : {0}" -f $dump)
        }
    } elseif ($Config.DumpAvant) {
        Journal 'ATTENTION' 'mysqldump introuvable : sauvegarde de la base ignoree.'
    }

    # --- 5. Mise en place du nouveau fichier pour DFS ---------
    Copy-Item -Path $tmp -Destination $Config.Destination -Force
    Journal 'INFO' ("Fichier depose pour DFS : {0}" -f $Config.Destination)

    # --- 6. Menage des vieilles sauvegardes ------------------
    if ($Config.GarderSauvegardes -gt 0 -and (Test-Path $Config.Backups)) {
        Get-ChildItem -Path $Config.Backups -File |
            Sort-Object LastWriteTime -Descending |
            Select-Object -Skip $Config.GarderSauvegardes |
            Remove-Item -Force -ErrorAction SilentlyContinue
    }

    Remove-Item $tmp -Force -ErrorAction SilentlyContinue
    Journal 'OK' 'Termine avec succes.'
    exit 0
}
catch {
    Journal 'ERREUR' $_.Exception.Message
    exit 1
}

# ============================================================
#  Agent balance TracaBoucher
#  Recupere les produits depuis l'application web et depose le fichier
#  dans le dossier surveille par RGI (qui l'envoie a la balance tout seul).
#  - N'agit QUE si quelque chose a change depuis la derniere fois.
#  - Ne remplace le fichier que si le telechargement est valide.
#  - Sauvegarde avant, et renvoie le resultat (OK/KO) a l'application.
#  - N'ecrit JAMAIS directement dans la base de DFS.
#
#  Lancement manuel :  powershell -ExecutionPolicy Bypass -File agent-balance.ps1
#  (Messages en ASCII volontairement, pour Windows PowerShell 5.1.)
# ============================================================
[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$racine     = Split-Path -Parent $MyInvocation.MyCommand.Path
$logFile    = Join-Path $racine 'agent-balance.log'
$statutFile = Join-Path $racine 'agent-statut.txt'
$hashFile   = Join-Path $racine 'dernier-hash.txt'

function Journal([string]$niveau, [string]$msg) {
    $ligne = ('{0}  [{1}]  {2}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $niveau, $msg)
    try { Add-Content -Path $logFile -Value $ligne -Encoding UTF8 } catch {}
    Write-Host $ligne
}

function Resultat([string]$etat, [string]$message, [int]$nb) {
    $suffixe = ''
    if ($nb -gt 0) { $suffixe = " ($nb produits)" }
    $txt = ('{0}  {1}  {2}{3}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $etat.ToUpper(), $message, $suffixe)
    try { Set-Content -Path $statutFile -Value $txt -Encoding UTF8 } catch {}
    if ($script:PingUrl) {
        try {
            Invoke-WebRequest -Uri $script:PingUrl -Method Post -TimeoutSec 30 -UseBasicParsing -Body @{
                token = $script:Token; etat = $etat; message = $message; nb = $nb
            } | Out-Null
        } catch { Journal 'ATTENTION' "Envoi de l'etat a l'application impossible (sans consequence)." }
    }
}

$script:PingUrl = $null
$script:Token   = $null

try {
    $configFile = Join-Path $racine 'config.ps1'
    if (-not (Test-Path $configFile)) { throw "config.ps1 introuvable. Lancez installer.bat." }
    . $configFile
    if (-not $Config -or -not $Config.Url -or $Config.Url -match 'COLLEZ_LE_JETON') { throw "config.ps1 incomplet : URL/jeton manquant." }

    if ($Config.Url -match 'token=([0-9a-fA-F]+)') { $script:Token = $matches[1] }
    if ($Config.PingUrl) { $script:PingUrl = $Config.PingUrl }
    elseif ($Config.Url -match '^(.*)/export\.php') { $script:PingUrl = $matches[1] + '/agent_ping.php' }

    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

    $destDir = Split-Path -Parent $Config.Destination
    foreach ($d in @($destDir, $Config.Backups)) {
        if ($d -and -not (Test-Path $d)) { New-Item -ItemType Directory -Force -Path $d | Out-Null }
    }

    $tmp = Join-Path $env:TEMP ("traca_{0}.csv" -f (Get-Date -Format 'yyyyMMddHHmmss'))
    Invoke-WebRequest -Uri $Config.Url -OutFile $tmp -UseBasicParsing -TimeoutSec 60

    if (-not (Test-Path $tmp) -or (Get-Item $tmp).Length -eq 0) { throw "Telechargement vide." }
    $premiere = Get-Content -Path $tmp -TotalCount 1 -Encoding UTF8
    if ($premiere -notmatch 'IdArticulo' -or $premiere -notmatch 'EANScanner') {
        throw "Reponse inattendue (pas l'export balance). Jeton invalide ou site indisponible."
    }
    $contenu  = Get-Content -Path $tmp -Raw -Encoding UTF8
    $nbLignes = ([regex]::Matches($contenu, "`n")).Count
    $hash     = (Get-FileHash -Path $tmp -Algorithm SHA256).Hash

    $ancien = ''
    if (Test-Path $hashFile) { $ancien = (Get-Content $hashFile -Raw).Trim() }
    if ($hash -eq $ancien) {
        Remove-Item $tmp -Force -ErrorAction SilentlyContinue
        Journal 'INFO' 'Aucun changement depuis la derniere synchro.'
        exit 0
    }

    Journal 'INFO' ("Changement detecte : {0} produit(s) a transmettre." -f $nbLignes)
    $horo = Get-Date -Format 'yyyyMMdd_HHmmss'

    if (Test-Path $Config.Destination) {
        Copy-Item -Path $Config.Destination -Destination (Join-Path $Config.Backups ("articles_{0}.csv" -f $horo)) -Force
    }

    if ($Config.DumpAvant -and $Config.MysqlDump -and (Test-Path $Config.MysqlDump)) {
        $dumpFile = Join-Path $Config.Backups ("sys_datos_dfs_{0}.sql" -f $horo)
        $argsDump = @("--host=$($Config.DbHost)", "--port=$($Config.DbPort)", "--user=$($Config.DbUser)",
                      "--password=$($Config.DbPass)", '--single-transaction', '--default-character-set=utf8', $Config.DbName)
        & $Config.MysqlDump @argsDump | Out-File -FilePath $dumpFile -Encoding UTF8
        if ($LASTEXITCODE -eq 0) { Journal 'INFO' 'Base DFS sauvegardee.' }
        else { Journal 'ATTENTION' ("mysqldump code {0} : sauvegarde base ignoree." -f $LASTEXITCODE) }
    }

    Copy-Item -Path $tmp -Destination $Config.Destination -Force
    Set-Content -Path $hashFile -Value $hash -Encoding ascii
    Remove-Item $tmp -Force -ErrorAction SilentlyContinue

    if ($Config.GarderSauvegardes -gt 0 -and (Test-Path $Config.Backups)) {
        Get-ChildItem -Path $Config.Backups -File | Sort-Object LastWriteTime -Descending |
            Select-Object -Skip $Config.GarderSauvegardes | Remove-Item -Force -ErrorAction SilentlyContinue
    }

    Journal 'OK' ("Fichier depose pour la balance : {0}" -f $Config.Destination)
    Resultat 'ok' 'Produits transmis a la balance.' $nbLignes
    exit 0
}
catch {
    Journal 'ERREUR' $_.Exception.Message
    Resultat 'ko' $_.Exception.Message 0
    exit 1
}

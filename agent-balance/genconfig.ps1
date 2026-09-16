# Génère config.ps1 à partir des variables d'environnement posées par installer.bat.
$ErrorActionPreference = 'Stop'
$racine = Split-Path -Parent $MyInvocation.MyCommand.Path

$url  = $env:AGENT_URL
if (-not $url -or $url -notmatch 'token=') { throw "URL invalide (elle doit contenir 'token=')." }

$dossier = $env:AGENT_DEST
if (-not $dossier) { $dossier = 'C:\DibalImport' }
$dossier = $dossier.TrimEnd('\', '/')
$dest    = Join-Path $dossier 'ARTICLES.TXT'
$backups = Join-Path $dossier 'backups'

$dbpass = $env:AGENT_DBPASS
$dump   = if ($dbpass) { '$true' } else { '$false' }

# Échappement des quotes simples pour l'écriture dans un littéral PowerShell
function q([string]$s) { return ($s -replace "'", "''") }

$contenu = @"
# Généré par installer.bat le $(Get-Date -Format 'yyyy-MM-dd HH:mm')
`$Config = @{
    Url         = '$(q $url)'
    Destination = '$(q $dest)'
    Backups     = '$(q $backups)'
    DumpAvant   = $dump
    MysqlDump   = 'C:\Program Files (x86)\MySQL\MySQL Server 5.0\bin\mysqldump.exe'
    DbHost      = 'localhost'
    DbPort      = 3307
    DbName      = 'sys_datos_dfs'
    DbUser      = 'root'
    DbPass      = '$(q $dbpass)'
    GarderSauvegardes = 30
    PingUrl     = ''
}
"@

Set-Content -Path (Join-Path $racine 'config.ps1') -Value $contenu -Encoding UTF8
Write-Host "config.ps1 ecrit (destination : $dest)"

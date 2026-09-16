# ============================================================
#  Enregistre l'agent comme tache planifiee Windows.
#  A lancer UNE FOIS, dans une fenetre PowerShell "en tant qu'administrateur".
#  Par defaut : toutes les 30 minutes.
# ============================================================
[CmdletBinding()]
param(
    [int]$IntervalleMinutes = 30,
    [string]$NomTache = 'TracaBoucher - Agent balance'
)

$racine = Split-Path -Parent $MyInvocation.MyCommand.Path
$script = Join-Path $racine 'agent-balance.ps1'
if (-not (Test-Path $script)) { throw "agent-balance.ps1 introuvable a cote de ce script." }

$action = New-ScheduledTaskAction -Execute 'powershell.exe' `
    -Argument ('-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "{0}"' -f $script)

$declencheur = New-ScheduledTaskTrigger -Once -At (Get-Date).Date.AddMinutes(1) `
    -RepetitionInterval (New-TimeSpan -Minutes $IntervalleMinutes)

$reglages = New-ScheduledTaskSettingsSet -StartWhenAvailable -DontStopOnIdleEnd `
    -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 10)

Register-ScheduledTask -TaskName $NomTache -Action $action -Trigger $declencheur `
    -Settings $reglages -RunLevel Highest -Force -User $env:USERNAME | Out-Null

Write-Host "Tache '$NomTache' enregistree : toutes les $IntervalleMinutes minutes."
Write-Host "Test immediat : Start-ScheduledTask -TaskName '$NomTache'"
Write-Host "Suppression   : Unregister-ScheduledTask -TaskName '$NomTache' -Confirm:`$false"

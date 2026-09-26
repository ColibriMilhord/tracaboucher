@echo off
setlocal
title Envoi des produits vers la balance
cd /d "%~dp0"

rem ============================================================
rem  Envoi immediat, en un double-clic, sans attendre la tache
rem  planifiee. Reutilise agent-balance.ps1 (meme verification,
rem  meme sauvegarde, meme compte rendu dans l'appli) avec -Forcer :
rem  le fichier est redepose meme si rien n'a change.
rem  Pas de goto : un .bat recupere en fins de ligne LF perd ses
rem  etiquettes, un bloc if/else n'a pas ce probleme.
rem ============================================================

echo.
echo ============================================================
echo   Envoi immediat des produits vers la balance
echo ============================================================
echo.

if not exist "%~dp0config.ps1" (
  echo   ^> L'agent n'est pas encore configure.
  echo     Lancez d'abord installer.bat, dans ce meme dossier.
) else (
  powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0agent-balance.ps1" -Forcer
  if errorlevel 1 (
    echo.
    echo   ^> ECHEC : la raison est sur la ligne [ERREUR] ci-dessus.
    echo     Rien n'a ete remplace : la balance garde ses produits actuels.
  ) else (
    echo.
    echo   ^> OK : fichier depose dans le dossier surveille par RGI.
    echo     Si RGI est lance, il l'importe et l'envoie a la balance
    echo     tout seul dans les instants qui suivent.
  )
)

echo.
pause
endlocal

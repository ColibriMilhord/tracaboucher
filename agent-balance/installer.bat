@echo off
chcp 65001 >nul
setlocal
title Installation de l'agent balance TracaBoucher
cd /d "%~dp0"

echo.
echo ============================================================
echo   Installation de l'agent balance TracaBoucher
echo ============================================================
echo.
echo Cet assistant configure l'envoi automatique de vos produits
echo vers l'etiqueteuse Dibal. Trois questions, puis c'est fini.
echo.

echo --- 1/3 : le lien de recuperation --------------------------
echo Dans l'application : Parametres ^> Pont automatique.
echo Copiez la ligne "URL de recuperation" et collez-la ici.
echo.
set "AGENT_URL="
set /p "AGENT_URL=Coller l'URL puis Entree : "
if not defined AGENT_URL (
  echo.
  echo   ^> Aucune URL saisie. Installation annulee.
  goto :fin
)

echo.
echo --- 2/3 : le dossier surveille par DFS ---------------------
echo Ou l'agent depose le fichier que DFS/RGI va lire.
echo Demandez ce dossier a votre installateur Dibal.
echo Laissez vide pour C:\DibalImport
echo.
set "AGENT_DEST="
set /p "AGENT_DEST=Dossier (Entree = C:\DibalImport) : "

echo.
echo --- 3/3 : sauvegarde de la base DFS (conseille) ------------
echo Mot de passe MySQL de DFS, pour une copie de securite
echo avant chaque envoi. Laissez vide pour ne pas sauvegarder.
echo.
set "AGENT_DBPASS="
set /p "AGENT_DBPASS=Mot de passe MySQL (Entree = ignorer) : "

echo.
echo ------------------------------------------------------------
echo   Configuration...
echo ------------------------------------------------------------
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0genconfig.ps1"
if errorlevel 1 (
  echo.
  echo   ^> ECHEC de la configuration. Verifiez l'URL collee.
  goto :fin
)

echo.
echo   Planification (toutes les 2 minutes)...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0installer-tache.ps1" -IntervalleMinutes 2 >nul 2>&1
if errorlevel 1 (
  echo   ^> La tache planifiee n'a pas pu etre creee.
  echo     Relancez ce fichier en tant qu'administrateur ^(clic droit^).
) else (
  echo   Tache planifiee creee.
)

echo.
echo   Test immediat...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0agent-balance.ps1" >nul 2>&1

echo.
echo ============================================================
echo   RESULTAT
echo ============================================================
if exist "%~dp0agent-statut.txt" (
  type "%~dp0agent-statut.txt"
  echo.
  echo   Si la ligne commence par OK : tout fonctionne.
  echo   Si elle commence par KO : la raison est indiquee ci-dessus.
) else (
  echo   Aucun resultat ecrit. Consultez agent-balance.log.
)
echo.
echo   L'agent tournera desormais tout seul toutes les 2 minutes.
echo   Le resultat s'affiche aussi dans l'appli : Assistant balance.
echo.

:fin
echo.
pause
endlocal

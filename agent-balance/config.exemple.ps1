# ============================================================
#  Agent balance TracaBoucher - configuration
#  Copiez ce fichier en "config.ps1" et adaptez les valeurs.
#  config.ps1 contient le jeton : ne le partagez pas.
# ============================================================

$Config = @{

    # --- Source : l'URL affichee dans l'appli (Parametres > Pont automatique) ---
    Url = 'https://causselot.fr/tracabilite/export.php?type=dfs_articulo&token=COLLEZ_LE_JETON_ICI'

    # --- Destination : le fichier que DGI/RGI de DFS va lire ---
    #     Demandez a votre installateur le dossier surveille par DFS.
    Destination = 'C:\DibalImport\articles.csv'

    # --- Sauvegardes : anciens fichiers + dumps de la base, horodates ---
    Backups = 'C:\DibalImport\backups'

    # --- Sauvegarde de la base DFS avant chaque import (fortement conseille) ---
    #     Lecture seule : ne modifie jamais la base, en fait juste une copie.
    DumpAvant = $true
    MysqlDump = 'C:\Program Files (x86)\MySQL\MySQL Server 5.0\bin\mysqldump.exe'
    DbHost    = 'localhost'
    DbPort    = 3307
    DbName    = 'sys_datos_dfs'
    DbUser    = 'root'
    DbPass    = 'MOT_DE_PASSE_MYSQL_DFS'

    # --- Combien de sauvegardes conserver (les plus anciennes sont effacees) ---
    GarderSauvegardes = 30
}

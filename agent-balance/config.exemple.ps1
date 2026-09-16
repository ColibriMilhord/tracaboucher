# ============================================================
#  Agent balance TracaBoucher - configuration
#  Normalement rempli automatiquement par installer.bat.
#  config.ps1 contient le jeton : ne le partagez pas.
# ============================================================

$Config = @{

    # --- URL affichée dans l'appli (Paramètres > Pont automatique) ---
    Url = 'https://causselot.fr/tracabilite/export.php?type=dfs_articulo&token=COLLEZ_LE_JETON_ICI'

    # --- Fichier lu par RGI (le "robot" de DFS surveille ce dossier) ---
    #     Nom par défaut attendu par DFS : ARTICLES.TXT
    #     Demandez le dossier surveillé à votre installateur Dibal.
    Destination = 'C:\DibalImport\ARTICLES.TXT'

    # --- Sauvegardes horodatées (anciens fichiers + base) ---
    Backups = 'C:\DibalImport\backups'

    # --- Sauvegarde de la base DFS avant chaque envoi (conseillé) ---
    #     Lecture seule : ne modifie jamais la base.
    DumpAvant = $true
    MysqlDump = 'C:\Program Files (x86)\MySQL\MySQL Server 5.0\bin\mysqldump.exe'
    DbHost    = 'localhost'
    DbPort    = 3307
    DbName    = 'sys_datos_dfs'
    DbUser    = 'root'
    DbPass    = 'MOT_DE_PASSE_MYSQL_DFS'

    # --- Nombre de sauvegardes à conserver ---
    GarderSauvegardes = 30

    # --- URL de retour d'état (laisser vide : déduite de Url) ---
    PingUrl = ''
}

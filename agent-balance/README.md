# Agent balance TracaBoucher

Petit programme qui tourne sur le **PC de l'atelier** (celui où est installé DFS).
Il récupère tout seul les produits depuis l'application web et dépose le fichier
là où DFS ira le lire. Il **ne touche jamais** directement à la base de DFS : il
se contente de déposer un fichier et de faire des sauvegardes.

## Ce qu'il fait, à chaque passage

1. Télécharge ARTICLES.TXT depuis l'application (lien + jeton sécurisé).
2. Vérifie que c'est bien ce fichier (sinon il ne remplace rien).
3. Sauvegarde l'ancien fichier, et fait une copie de la base DFS (lecture seule).
4. Dépose le nouveau fichier là où DFS le lira (DGI/RGI).
5. Écrit ce qu'il a fait dans `agent-balance.log`.

RGI (livré avec DFS) importe ensuite ce fichier tout seul, une fois l'import
décrit dans DGI. Les réglages DGI, RGI et DLD sont détaillés dans l'application,
**Assistant balance**, et dans `MAPPING_DFS.md`.

## Installation (une seule fois)

1. Dans l'application : **Assistant balance → Télécharger l'agent** (administrateur).
   Le zip contient déjà l'URL de récupération et son jeton (`url.txt`).
2. Sur le PC de l'atelier (celui où est DFS) : clic droit sur le zip → *Extraire tout*,
   par ex. dans `C:\TracaBoucher`.
3. Clic droit sur `installer.bat` → *Exécuter en tant qu'administrateur* (pour créer
   la tâche planifiée). Si Windows affiche « Windows a protégé votre ordinateur » :
   *Informations complémentaires* → *Exécuter quand même*. Deux questions :
   - le **dossier surveillé par RGI** (Entrée = `C:\DibalImport`) ;
   - le **mot de passe MySQL** de DFS pour la sauvegarde (facultatif, Entrée = ignorer).

   Sans `url.txt` (dossier copié à la main), il demande aussi de coller l'URL de
   **Paramètres → Pont automatique**.

   Il configure tout, planifie l'agent **toutes les 2 minutes**, fait un test et
   affiche **OK**, ou la **raison** en cas de problème.

L'agent tourne ensuite tout seul : dès que vous modifiez un prix dans l'appli, il
le détecte à la synchro suivante et dépose le fichier que DFS (RGI) envoie à la
balance. Le résultat s'affiche aussi dans l'appli (**Assistant balance**).

## Envoyer tout de suite (double-clic)

Pour ne pas attendre la synchro suivante — après un changement de prix, ou si la
balance a été réinitialisée : **double-cliquez sur `maj_dfs.bat`**.

Il fait exactement ce que fait l'agent planifié (téléchargement, vérification,
sauvegarde, compte rendu dans l'appli), à une différence près : il redépose le
fichier **même si rien n'a changé**. La fenêtre affiche **OK**, ou la raison de
l'échec — et en cas d'échec, rien n'est remplacé.

RGI prend ensuite le relais tout seul, **à condition d'être lancé** sur ce PC :
il repère `ARTICLES.TXT`, l'importe dans DFS, l'envoie à la balance et le range
dans son dossier des fichiers traités.

### En cas de besoin, à la main

- Relancer une fois : `powershell -ExecutionPolicy Bypass -File .\agent-balance.ps1`
  (ajouter `-Forcer` pour redéposer le fichier même sans changement)
- (Re)planifier : `powershell -ExecutionPolicy Bypass -File .\installer-tache.ps1 -IntervalleMinutes 2`
- La config générée est dans `config.ps1` (modèle : `config.exemple.ps1`).

## Sécurité

- Le jeton donne accès en **lecture seule** aux produits, rien d'autre. Il se
  révoque et se régénère à tout moment depuis Paramètres.
- L'agent ne fait **aucune écriture** dans la base de DFS. La seule opération sur
  la base est un `mysqldump` de sauvegarde (lecture).
- `config.ps1` et `url.txt` contiennent le jeton (et `config.ps1` le mot de passe
  MySQL) : gardez-les sur le PC de l'atelier uniquement, ne les partagez pas. Même
  chose pour le zip téléchargé.

## En cas de souci

Tout est tracé dans `agent-balance.log`. Les messages `[ERREUR]` expliquent la
cause (jeton invalide, dossier introuvable, pas de réseau…). « Réponse inattendue »
après une mise à jour de l'application : l'agent date d'avant la version 2.7 et ne
reconnaît pas le nouveau fichier, retéléchargez-le et relancez `installer.bat`.
« mysqldump code … : sauvegarde base ignorée » : le MySQL de DFS écoute sur le port
3306 (voir son `my.ini`). Un agent installé avant la version 2.7.2 visait 3307 :
relancez `installer.bat`, ou corrigez `DbPort` dans `config.ps1`. Le fichier destiné à
DFS n'est **jamais** remplacé si le téléchargement échoue : la dernière version
valide reste en place.

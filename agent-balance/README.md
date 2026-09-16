# Agent balance TracaBoucher

Petit programme qui tourne sur le **PC de l'atelier** (celui où est installé DFS).
Il récupère tout seul les produits depuis l'application web et dépose le fichier
là où DFS ira le lire. Il **ne touche jamais** directement à la base de DFS : il
se contente de déposer un fichier et de faire des sauvegardes.

## Ce qu'il fait, à chaque passage

1. Télécharge le fichier des produits depuis l'application (lien + jeton sécurisé).
2. Vérifie que le téléchargement est valide (sinon il ne remplace rien).
3. Sauvegarde l'ancien fichier, et fait une copie de la base DFS (lecture seule).
4. Dépose le nouveau fichier là où DFS le lira (DGI/RGI).
5. Écrit ce qu'il a fait dans `agent-balance.log`.

DFS importe ensuite ce fichier — soit automatiquement s'il surveille le dossier
(RGI), soit en un clic dans DGI. C'est votre installateur qui règle ce point une
fois.

## Installation (une seule fois)

1. Copiez le dossier `agent-balance` sur le PC de l'atelier (celui où est DFS),
   par ex. dans `C:\TracaBoucher\agent-balance`.
2. Dans l'application web : **Paramètres → Pont automatique → Générer un jeton**,
   et copiez la ligne **URL de récupération**.
3. **Double-cliquez sur `installer.bat`** (idéalement clic droit → *Exécuter en tant
   qu'administrateur*, pour créer la tâche planifiée). Il pose trois questions :
   - collez l'**URL de récupération** ;
   - indiquez le **dossier surveillé par DFS** (demandez-le à votre installateur) ;
   - le **mot de passe MySQL** de DFS pour la sauvegarde (facultatif).

   Il configure tout, planifie l'agent **toutes les 2 minutes**, fait un test et
   affiche **OK**, ou la **raison** en cas de problème.

L'agent tourne ensuite tout seul : dès que vous modifiez un prix dans l'appli, il
le détecte à la synchro suivante et dépose le fichier que DFS (RGI) envoie à la
balance. Le résultat s'affiche aussi dans l'appli (**Assistant balance**).

### En cas de besoin, à la main

- Relancer une fois : `powershell -ExecutionPolicy Bypass -File .\agent-balance.ps1`
- (Re)planifier : `powershell -ExecutionPolicy Bypass -File .\installer-tache.ps1 -IntervalleMinutes 2`
- La config générée est dans `config.ps1` (modèle : `config.exemple.ps1`).

## Sécurité

- Le jeton donne accès en **lecture seule** aux produits, rien d'autre. Il se
  révoque et se régénère à tout moment depuis Paramètres.
- L'agent ne fait **aucune écriture** dans la base de DFS. La seule opération sur
  la base est un `mysqldump` de sauvegarde (lecture).
- `config.ps1` contient le jeton et le mot de passe MySQL : gardez ce fichier sur
  le PC de l'atelier uniquement, ne le partagez pas.

## En cas de souci

Tout est tracé dans `agent-balance.log`. Les messages `[ERREUR]` expliquent la
cause (jeton invalide, dossier introuvable, pas de réseau…). Le fichier destiné à
DFS n'est **jamais** remplacé si le téléchargement échoue : la dernière version
valide reste en place.

# TraçaBoucher v2 — traçabilité atelier de transformation

Application PHP / MySQL. Deux interfaces de saisie couvrent toute la traçabilité
de l'atelier : **Entrée** (réception matières premières) et **Fabrication** (sortie).

## Installation sur Hostinger

1. **hPanel > Bases de données MySQL** : créer une base et un utilisateur.
   Noter le nom de base (`u123456789_xxx`), l'utilisateur et le mot de passe.
2. Reporter ces trois valeurs dans `config.php` (`DB_NAME`, `DB_USER`, `DB_PASS`).
   `DB_HOST` reste `localhost`.
3. Téléverser tous les fichiers dans `public_html/` (ou le dossier du domaine).
4. Ouvrir `https://votre-domaine/install.php` → **Créer les tables**.
5. Ouvrir `login.php` → créer le compte **administrateur** (le premier compte créé
   est automatiquement administrateur).
6. **Supprimer `install.php` et `install_v2.sql` du serveur.**
7. Créer les comptes des opérateurs dans **Utilisateurs** (icône groupe, en haut) —
   ou, une fois la connexion unique ci-dessous en place, depuis app.causselot.fr.

## Connexion unique (SSO) avec CAUSSELOT

Depuis la refonte de `app.causselot.fr`, les comptes et les sessions sont
partagés entre les deux applications : se connecter une fois sur
`app.causselot.fr` suffit pour être déjà connecté ici, et inversement.

1. **hPanel > Bases de données MySQL** : associer l'utilisateur MySQL de
   TraçaBoucher (`DB_USER` ci-dessus) à la base utilisée par
   `app.causselot.fr` (« Gérer les utilisateurs » sur cette base).
2. Migrer les comptes déjà créés ici (`utilisateurs` local) vers la table
   `utilisateurs` de la base causselot (mêmes colonnes `identifiant`,
   `nom`, `mot_de_passe` — le hash se recopie tel quel —, `role` = `admin`
   si c'était `admin`, sinon `atelier`, `actif`).
3. Dans `config.local.php`, définir `DB_NAME_CAUSSELOT` (nom de la base
   causselot) et `CAUSSELOT_URL` (`https://app.causselot.fr/`) — voir
   `config.example.php`.
4. **Paramètres > Connexion unique > Reprise des comptes** : recopie dans
   la base partagée les comptes qui n'existaient que dans TraçaBoucher,
   en conservant leurs mots de passe. Sans cette étape, leurs titulaires
   ne peuvent plus se connecter. La page est relançable et n'écrase
   jamais un identifiant déjà présent.

Une fois ces étapes faites, `includes/auth.php` bascule seul sur les
comptes et les sessions partagés (repli automatique sur le comportement
précédent tant que ce n'est pas fait, donc rien ne casse entre-temps), et
**Utilisateurs** redirige vers app.causselot.fr, désormais seul endroit où
créer ou modifier un compte (les rôles y sont `admin`/`atelier`/`client`,
pas `admin`/`operateur`).

### Rôles : vocabulaire partagé

La table des comptes est commune à app.causselot.fr et à l'ancien site
colibrietcompagnie, qui n'écrivent pas les mêmes libellés.
`role_canonique()` (dans `includes/auth.php`) ramène `administrateur`
vers `admin`, `preparateur`/`livreur`/`operateur` vers `atelier`. Sans
elle, un administrateur du portail arrivait ici avec un rôle que rien ne
reconnaissait, et tous les écrans d'administration disparaissaient de sa
navigation — sans le moindre message.

Ce qui demande `admin` : produits, composition, paramètres, comptes.
Tout le reste — saisie, traçabilité, assistant balance, exports — est
ouvert à l'atelier.

## Version affichée

`version.php` porte le numéro de version et sa date. **Tout commit qui
change quelque chose de visible incrémente ce fichier** — c'est ce qui
permet de vérifier, après un déploiement, que le serveur fait bien
tourner le dernier code, et non un déploiement resté en arrière ou une
copie de fichiers figée lors d'un déménagement de domaine.

La version apparaît en bas de la barre latérale sur ordinateur, sur la
page de connexion, sur `maj.php` et en bas de **Paramètres**.

Convention : `MAJEURE.MINEURE.CORRECTIF` — mineure pour une
fonctionnalité ou la refonte d'un écran, correctif pour un bug ou un
ajustement d'ergonomie.

## Atelier : ouvrir, étiqueter, clôturer

La question pratique du boucher au tablier : pour étiqueter, il faut le
numéro de lot — mais le numéro n'existait qu'une fois la fabrication
saisie en entier, poids produit compris. Donc il fallait soit étiqueter
à l'aveugle, soit ré-étiqueter.

**`atelier.php`**, trois étapes dans l'ordre où le travail se fait :

1. **Ouvrir un lot** — produit et matières premières, rien d'autre. Le
   numéro apparaît aussitôt, en grand, avec le bouton d'étiquette
   dessous. Conditionnement, conservation et DLC viennent du référentiel
   produits (`dlc_jours`) : ce sont des caractéristiques du produit, pas
   des questions à poser au tablier.
2. **Étiqueter** — tous les lots ouverts, un appui par étiquette, autant
   de fois qu'il y a de barquettes.
3. **Clôturer** — poids produit et DLC, en fin de fabrication. Le lot
   entre alors au registre.

**Plusieurs lots cohabitent**, c'est le quotidien de l'atelier. La
numérotation suit la règle déjà en place : code du jour + occurrence
(`031026-1`, `-2`, `-3`). La catégorie n'entre pas dans le numéro — un
numéro de lot ne doit jamais changer, les familles de produits si — mais
elle s'affiche collée à lui partout.

**Tant qu'un lot est ouvert, sa quantité n'est pas une donnée.** Ni
l'étiquette ni le registre ne l'affichent : `statut` porte le sens, pas
une valeur à zéro. La colonne `quantite` n'a volontairement pas été
modifiée par la migration — son type exact ne figure pas dans le dépôt,
et une colonne de registre réglementaire ne se retouche pas à l'aveugle.

**Deux personnes en même temps.** L'écran interroge `atelier_etat.php`
toutes les 15 secondes et annonce par un bandeau le lot qu'un collègue
vient d'ouvrir, sans recharger la page sous les doigts de celui qui
saisit. Et si deux personnes clôturent le même lot, la seconde est
prévenue : la clôture est un `UPDATE … WHERE statut='ouvert'`, dont le
`rowCount` à zéro dit que quelqu'un est passé avant.

## Saisie différée : importer les étiquettes de la balance

L'autre parcours : on étiquette d'abord à la balance, on saisit ensuite.
**`import_etiquettes.php`**, un écran par manipulation.

1. **L'agent** — « est-il installé sur le PC de l'atelier ? ». Répondre
   oui clôt le sujet pour cet utilisateur (`reglages_utilisateur`) ;
   répondre non mène à l'installation guidée.
2. **Le fichier** — où le trouver dans DFS, puis on le dépose.
3. **Les colonnes** — reconnues par les intitulés (français et espagnol :
   DFS est un logiciel Dibal), sinon par le contenu, sinon corrigées sur
   place. Le fichier reste en session, donc on corrige sans redéposer.
4. **Rattacher** — les étiquettes rejoignent leur lot ; celles qui ne
   correspondent à rien restent visibles et se rattachent à la main.

**Le numéro de lot naît dans TraçaBoucher, jamais à la balance.**
L'import ne crée pas de lot : il le complète. Le poids pesé est ensuite
proposé comme quantité produite à la clôture — plus rien à retaper.

Le rattachement se fait sur le numéro de lot lu dans le fichier ; à
défaut, sur le couple produit + date. **Deux lots du même produit ouverts
le même jour ne sont jamais départagés automatiquement** : c'est
précisément ce que l'occurrence sert à distinguer, et ce n'est pas à
l'application d'en décider.

Moteur de lecture : `includes/csv.php`, portage de celui éprouvé sur les
relevés bancaires d'app.causselot.fr, généralisé aux rôles de colonnes.

## Matériel : le PC de l'atelier

**`materiel.php`** remplace le README qu'il fallait aller chercher dans
le dépôt. Quatre étapes cochées au fur et à mesure : générer le jeton,
télécharger `agent-balance.zip` (empaqueté à la volée depuis le dépôt,
donc toujours à la version du serveur, et sans `config.ps1` qui porte les
secrets du poste), lancer `installer.bat`, vérifier que l'agent répond.

La page affiche aussi l'**inventaire de la base DFS** : l'agent liste une
fois par jour les tables de `sys_datos_dfs` et leur volume, et les POSTe
sur `agent_inventaire.php`. Uniquement des noms et des comptages, aucune
donnée. C'est ce qui permettra de savoir où DFS range les pesées, et donc
de remonter les étiquettes automatiquement — sans deviner un nom de table.

## Numérotation des lots

| | Format | Exemple |
|---|---|---|
| Entrée | `TYPE-JJMMAA` | `JB-100726` |
| Entrée (2ᵉ lot même type / même jour) | `TYPE-JJMMAA-n` | `JB-100726-2` |
| Fabrication | `JJMMAA-n` | `100726-1` |

Le préfixe des n° de fabrication est configurable dans **Paramètres**
(vide par défaut ; avec « F » on obtient `F100726-1`).

Les codes de type (`JB`, `VA`, `L`, `PS`…) se gèrent dans **Paramètres > Types de
matière première**. La catégorie « Viande » déclenche l'alerte de température
4 °C ± 2 °C sur le tableau de bord.

## Pages

| Fichier | Rôle |
|---|---|
| `index.php` | Tableau de bord, alertes température, derniers lots |
| `entrees.php` | Réception matières premières (liste / création / fiche) |
| `fabrications.php` | Suivi des fabrications, avec lots d'entrée utilisés |
| `tracabilite.php` | Recherche ascendante et descendante |
| `etiquette.php` | Vue imprimable d'un n° de lot |
| `exports.php` | Écran des exports — registres et fichiers balance |
| `export.php` | Génération des CSV (fiche de lot, registres, DFS) |
| `parametres.php` | Types de matière, préfixe, agréments, jeton — **admin** |
| `utilisateurs.php` | Comptes opérateurs — **admin** |
| `archive_v1/` | Ancienne version (espèces / ingrédients / plats), non accessible |

## Champs

**Entrée** — obligatoires : date, type, température, fournisseur.
Facultatifs : poids, photo, forme, état, n° de lot fournisseur, notes.

**Fabrication** — obligatoires : date, nom du produit, quantité fabriquée,
au moins un lot d'entrée utilisé, conditionnement, conservation.
Facultatifs : DLC, cuisson (°C / durée), refroidissement, photo, notes.

Chaque lot est signé du nom de l'opérateur connecté et horodaté ; ces deux
informations apparaissent sur la fiche et dans les exports.

## Sauvegarde

hPanel > Sauvegardes, ou export manuel de la base via phpMyAdmin.
Les photos sont dans `uploads/`.

## Usage sur téléphone

L'application est prévue pour un usage majoritairement mobile.

**À installer sur l'écran d'accueil** : ouvrir `https://causselot.fr/tracabilite/` dans
Safari (iPhone) ou Chrome (Android), puis « Partager > Sur l'écran d'accueil » /
« Installer l'application ». Elle s'ouvre alors en plein écran, sans barre de navigateur,
avec deux raccourcis directs vers Nouvelle entrée et Nouvelle fabrication.

**Photos** : deux boutons. « Prendre une photo » ouvre l'appareil photo intégré à
l'application (getUserMedia) plutôt que l'appli caméra du système, dont le viseur reste
parfois noir sous Chrome ; si la caméra ne démarre pas, le motif exact est affiché
(refusée, occupée par une autre appli, absente). « Choisir un fichier » laisse le téléphone
proposer Galerie / Fichiers. L'image est réduite à 1600 px et convertie en JPEG **dans le navigateur** avant
l'envoi : une photo de 4 Mo part en 200 Ko environ. Si le navigateur ne sait pas décoder le
fichier, l'original est envoyé tel quel et c'est le serveur qui le réduit (GD). Le résultat
de la réduction est contrôlé avant envoi : une image vide ou entièrement noire — ce que
produit iOS quand il purge le canvas sur une photo de 12 Mpx — est rejetée au profit de
l'original. Et si la photo échoue
malgré tout, **le lot est quand même enregistré** et un message explique ce qui s'est passé.

**Sélection des lots d'entrée** : sur la fiche de fabrication, le choix se fait via une
recherche plein écran (n° de lot, type ou fournisseur) plutôt qu'une longue liste déroulante.

## Référentiel produits et balance Dibal LP-545

L'application est le référentiel : les produits y sont créés, la balance est alimentée
depuis elle. Chaque produit reçoit automatiquement un **PLU à 4 chiffres** et un
**EAN-13 interne** (préfixe 2, clé de contrôle calculée), les deux formats exigés par DFS.
Ces codes internes conviennent tant que les produits restent en vente directe ; une revente
par un autre commerce imposerait de vrais codes GS1, auquel cas seule la colonne EAN change.

**Pages** : `produits.php` (admin) pour le catalogue, `maj.php` (admin) pour appliquer les
migrations de `migrations/` — sans effet si elles sont déjà passées, donc relançable.

**Exports vers DFS**, depuis Paramètres ou la page Produits :

| Export | Contenu |
|---|---|
| `export.php?type=dfs_articles` | PLU, EAN13, libellés, famille, prix/kg, durée de vie, classe de traçabilité |
| `export.php?type=dfs_lots` | lots fabriqués du jour, avec PLU, DLC, conditionnement et lots d'entrée utilisés |

Ces fichiers sont en CSV point-virgule / UTF-8. Le mapping des colonnes se paramètre une
seule fois dans **DGI** (livré avec DFS) ; **RGI** applique ensuite l'import vers la LP-545.
Le format des colonnes est donc libre côté application.

## Bio : composition et taux (RUE 2018/848)

`recette.php` (admin, accessible depuis la fiche produit) enregistre la composition d'une
fabrication type : libellé, quantité, nature et statut bio de chaque ingrédient.

Le taux est calculé sur les **seuls ingrédients agricoles** ; l'eau et le sel en sont exclus
des deux termes du ratio, conformément au règlement. Trois issues :

| Taux | Ce que le produit peut afficher |
|---|---|
| ≥ 95 % | « bio » dans la dénomination, Eurofeuille, code certificateur, origine agricole |
| 0 < taux < 95 % | astérisques dans la liste d'ingrédients uniquement — ni logo, ni allégation |
| 0 % | aucune mention bio |

L'export `dfs_articles` porte `MENTION_BIO`, `TAUX_BIO`, `INGREDIENTS` (poids décroissant,
astérisques sur les ingrédients bio) et, **seulement au-dessus du seuil**, le code
certificateur et l'origine agricole — un produit sous le seuil ne peut donc pas recevoir
par erreur les métadonnées de l'Eurofeuille.

Le code de l'organisme certificateur et l'origine agricole se règlent dans Paramètres.

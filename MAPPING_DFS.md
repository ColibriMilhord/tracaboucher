# Correspondance TraçaBoucher → DFS (Dibal LP-545)

Sources : manuels Dibal **DGI / RGI** (49-MDRGI000EN10, 17/07/2015) et **DLD**
(49-MDLD500EN08). Les libellés d'écran sont cités en anglais, comme dans ces
manuels ; sur un DFS en français, ils sont traduits (l'Assistant balance les
désigne en français, avec ce nom anglais en repère). La marche à suivre pas à pas, pour l'utilisateur, est dans l'application :
**Assistant balance** (`guide.php`).

## Le fichier : ARTICLES.TXT

`export.php?type=dfs_articulo` (généré par `includes/dfs.php`), déposé par l'agent
dans `C:\DibalImport` ou téléchargé à la main. RGI surveille ce dossier, importe le
fichier dans DFS puis l'envoie à la balance.

| Règle | Pourquoi (manuel DGI/RGI) |
|---|---|
| Séparateur `;`, **aucun guillemet** | DGI prend un séparateur d'un caractère et ne connaît pas de délimiteur de texte : un libellé entre guillemets serait imprimé avec. Un `;` dans un texte devient `,`. |
| 1re ligne = titres des colonnes | Sautée par *Initial line = 1*. Chaque titre est le nom du champ DGI à choisir. |
| Name et Name 2 : 20 caractères | Le libellé court est réparti sur les deux, coupé entre deux mots. |
| Text 01 à Text 08 : 24 caractères | D'où l'agrément sur sa propre ligne, sous « Abattu en » et « Découpé en ». |
| G Text : 2 048 caractères | Reçoit la liste d'ingrédients. |
| Prix avec un point décimal (`11.30`) | Type DGI *Numeric with dot as decimal mark*. |
| UTF-8 avec BOM, fins de ligne CRLF | Repli ANSI (Windows-1252) dans Paramètres si les accents sortent mal. |

RGI écarte toute ligne dont une donnée est invalide (§ 5.2.6) : l'application coupe
elle-même ce qui dépasse et le signale à l'étape 1 de l'Assistant balance, plutôt
que de laisser un produit disparaître de la balance.

## Colonnes → champs DGI

| N° | Champ DGI | Type DGI | Contenu |
|---|---|---|---|
| 0 | Code | Numeric | PLU (6 chiffres au plus) |
| 1 | Type | Numeric | 1 = vendu au poids |
| 2 | Name | Text | libellé, 1re ligne |
| 3 | Name 2 | Text | libellé, 2e ligne |
| 4 | Price | Numeric with dot as decimal mark | prix TTC au kg |
| 5 | Expiration days | Numeric | durée de vie : la balance calcule la DLC |
| 6 | G Text | Text | ingrédients, astérisques bio |
| 7 | Text 01 | Text | « Origine : X », ou « Né en : X » |
| 8 | Text 02 | Text | « Élevé en : X » (vide si Origine) |
| 9 | Text 03 | Text | « Abattu en : X » |
| 10 | Text 04 | Text | agrément de l'abattoir |
| 11 | Text 05 | Text | « Découpé en : X » |
| 12 | Text 06 | Text | agrément de l'atelier |
| 13 | Text 07 | Text | code de l'organisme certificateur (produit ≥ 95 % bio) |
| 14 | Text 08 | Text | origine agricole (produit ≥ 95 % bio) |
| 15 | Label format | Numeric | n° du format d'étiquette — seulement s'il est réglé dans Paramètres |

Text 01 à 06 ne sont remplis que pour la classe `viande_bovine` (règlement
1760/2000), Text 07 et 08 que pour un produit à 95 % bio ou plus (RUE 2018/848).

**Non envoyés** : *Class* et *Section* attendent un numéro DFS (2 chiffres), pas
nos libellés ; *EAN Scanner* n'est pas nécessaire, la balance composant elle-même
le code-barres imprimé à partir du PLU, du poids et du prix.

## Réglages DGI (une fois)

*Design General Integration*, utilisateur et mot de passe d'usine : `general`.

| Menu | Réglage |
|---|---|
| Imports → Créer | Name `TracaBoucher` · File type `Articles` · Initial line `1` · Fields separator `;` · Operation type `Data to be added/eliminated` · Load file `C:\DibalImport` · File to import `ARTICLES*.TXT` |
| (même écran) | choisir un ARTICLES.TXT → *Generate List of Fields* → *Continue* → associer les champs du tableau ci-dessus |
| Import configuration | actif toute la journée ; « *Communicate importation with scales selected in DFS* » décoché |
| Activating imports | cocher TracaBoucher |

`ARTICLES*.TXT` accepte aussi « ARTICLES (1).TXT » quand un navigateur renomme le
fichier téléchargé. Le réglage est enregistré dans `LineData.xml` : copié dans le
dossier de RGI d'un autre PC, il évite de le refaire.

## RGI

*Run General Integration*, à laisser tourner (raccourci à mettre dans
`shell:startup`). Après import, le fichier quitte le dossier (copie dans
*ProcessedFiles*). Lignes refusées : *Show report*, dossier `RGI\Reports`, journal
`RGI\Logs\AAAAMMJJ.log`. Balance éteinte pendant l'envoi : *Sending modifications*.

## Étiquette (DLD)

Items → *Item name*, *Name 2*, *Text G*, *Text 1* à *Text 8* ; Dates → *Expiry
date* ; Bar Codes → *EAN 13*. L'Eurofeuille se charge dans Logos (largeur multiple
de 8, 432 px au plus). Envoi : *Shipping → Label format* ≥ 21. DLD tourne 90 jours
à l'essai, puis demande une licence Dibal.

## Important

Ne jamais créer ni modifier d'article directement dans DFS, ni écrire dans
`sys_datos_dfs` à la main : l'application est le référentiel, DFS en est le
destinataire via DGI/RGI. Un article saisi côté DFS deviendrait orphelin de la
traçabilité. Seule exception : un produit retiré de l'application reste dans la
balance (le fichier ne contient que les produits actifs) ; on le supprime dans DFS.

# Correspondance TraçaBoucher → DFS (Dibal LP-545)

Base DFS : `sys_datos_dfs` (MySQL, port 3307). Table cible : `dat_articulo`.

L'export **`export.php?type=dfs_articulo`** produit un CSV dont les en-têtes sont
déjà les noms de champs DFS : le mapping dans DGI est donc une correspondance
1:1, colonne par colonne.

| Colonne du fichier | Champ DFS `dat_articulo` | Remarque |
|---|---|---|
| IdArticulo | `IdArticulo` | = PLU sans zéros de tête |
| PLUNumber | `PLUNumber` | = PLU |
| Descripcion | `Descripcion` | libellé, 100 car. max |
| Descripcion1 | `Descripcion1` | libellé court |
| IdTipo | `IdTipo` | 1 = article au poids |
| PrecioConIVA | `PrecioConIVA` | prix TTC au kg |
| PrecioEstandar | `PrecioEstandar` | idem |
| DiasCaducidad | `DiasCaducidad` | durée de vie en jours → DLC calculée par la balance |
| EANScanner | `EANScanner` | EAN-13 interne (préfixe 2) |
| Texto1 | `Texto1` | liste d'ingrédients (astérisques bio) |
| Texto2 | `Texto2` | mentions d'origine bovine (né / élevé / abattu / découpé) |
| Texto3 | `Texto3` | mention bio + code certificateur, si le seuil de 95 % est atteint |
| CLASE_NOMBRE | `IdClase` | **à mapper à la main dans DGI** : les IdClase DFS n'existent pas encore |
| FAMILIA | `IdFamilia` / `IdSubFamilia` | à mapper selon l'arborescence DFS |

## À faire côté DFS, une fois

1. Choisir la langue française : **DFS General → Idioma → Français**.
2. Déclarer la balance : **Vue Magasin → Structure → Balance**, modèle série 500.
3. Créer la (ou les) classe(s) de traçabilité : **Programmation → Textes → Classes**
   (une classe « viande bovine » pour les mentions du règlement 1760/2000).
4. Concevoir le mapping d'import : **Programmation → Import/Export → DGI**,
   en chargeant le fichier `dfs_dat_articulo_*.csv` et en associant chaque colonne
   au champ `dat_articulo` du tableau ci-dessus.
5. `CLASE_NOMBRE` et `FAMILIA` sont les deux seules colonnes à relier manuellement
   à un identifiant DFS ; toutes les autres sont des correspondances directes.

## Important

Ne jamais créer d'article directement dans DFS ni écrire dans `sys_datos_dfs` à la
main : l'application est le référentiel, DFS en est le destinataire via DGI/RGI.
Un article saisi côté DFS deviendrait orphelin de la traçabilité.

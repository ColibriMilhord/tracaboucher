-- ============================================================
--  Morceaux de viande bovine : produits mono-ingredient, bio a 100%.
--  Un steak/roti = un seul ingredient agricole (la viande), bio.
--  On cree une ligne de composition unique par morceau qui n'en a
--  pas encore, ce qui fait passer taux_bio() a 100% (Eurofeuille).
--
--  La sous-requete NOT EXISTS est encapsulee dans une table derivee
--  (alias x) pour rester compatible MySQL comme MariaDB.
--  Relançable : ne recree pas de ligne pour un produit deja compose.
-- ============================================================
INSERT INTO `recette_lignes` (`produit_id`,`libelle`,`quantite`,`unite`,`nature`,`bio`,`ordre`)
SELECT p.id,
       CONCAT('Viande de ', LOWER(p.famille)),
       1.000, 'kg', 'agricole', 1, 10
FROM `produits` p
WHERE p.classe_traca = 'viande_bovine'
  AND NOT EXISTS (
      SELECT 1 FROM (SELECT produit_id FROM `recette_lignes`) x
      WHERE x.produit_id = p.id
  );

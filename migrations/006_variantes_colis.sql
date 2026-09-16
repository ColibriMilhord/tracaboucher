-- ============================================================
--  006 : eclatement des cotes veau (avec os / sans os) + colis.
--  Idempotent : UPDATE sur l'ancien libelle (no-op au 2e passage),
--  INSERT IGNORE sur PLU fixes, lignes bio via NOT EXISTS.
-- ============================================================

-- Fourchettes -> variante 'avec os'
UPDATE `produits` SET `libelle`='Côte découverte avec os', `libelle_court`='Côte découverte avec os' WHERE `libelle`='Côte découverte avec ou sans os';
UPDATE `produits` SET `libelle`='Côte première avec os', `libelle_court`='Côte première avec os' WHERE `libelle`='Côte première avec ou sans os';
UPDATE `produits` SET `libelle`='Côte filet avec os', `libelle_court`='Côte filet avec os' WHERE `libelle`='Côte filet avec ou sans os';
UPDATE `produits` SET `libelle`='Plat de côte avec os (veau)', `libelle_court`='Plat de côte avec os (veau)' WHERE `libelle`='Plat de côte avec ou sans os (veau)';

-- Nouveaux produits (sans os + colis), tous viande bovine
INSERT IGNORE INTO `produits` (`plu`,`ean13`,`libelle`,`libelle_court`,`famille`,`classe_traca`,`prix_kg`,`conditionnement`,`conservation`,`actif`,`ordre`) VALUES ('0065','2000650000007','Côte découverte sans os','Côte découverte sans os','Jeune bovin','viande_bovine',23.7,'barquette','froid_positif',1,650);
INSERT IGNORE INTO `produits` (`plu`,`ean13`,`libelle`,`libelle_court`,`famille`,`classe_traca`,`prix_kg`,`conditionnement`,`conservation`,`actif`,`ordre`) VALUES ('0066','2000660000004','Côte première sans os','Côte première sans os','Jeune bovin','viande_bovine',29,'barquette','froid_positif',1,660);
INSERT IGNORE INTO `produits` (`plu`,`ean13`,`libelle`,`libelle_court`,`famille`,`classe_traca`,`prix_kg`,`conditionnement`,`conservation`,`actif`,`ordre`) VALUES ('0067','2000670000001','Côte filet sans os','Côte filet sans os','Jeune bovin','viande_bovine',30.5,'barquette','froid_positif',1,670);
INSERT IGNORE INTO `produits` (`plu`,`ean13`,`libelle`,`libelle_court`,`famille`,`classe_traca`,`prix_kg`,`conditionnement`,`conservation`,`actif`,`ordre`) VALUES ('0068','2000680000008','Plat de côte sans os (veau)','Plat de côte sans os (veau)','Jeune bovin','viande_bovine',11.3,'barquette','froid_positif',1,680);
INSERT IGNORE INTO `produits` (`plu`,`ean13`,`libelle`,`libelle_court`,`famille`,`classe_traca`,`prix_kg`,`conditionnement`,`conservation`,`actif`,`ordre`) VALUES ('0069','2000690000005','Colis jeune bovin classique 5 kg','Colis jeune bovin classique 5 kg','Colis','viande_bovine',20,'barquette','froid_positif',1,690);
INSERT IGNORE INTO `produits` (`plu`,`ean13`,`libelle`,`libelle_court`,`famille`,`classe_traca`,`prix_kg`,`conditionnement`,`conservation`,`actif`,`ordre`) VALUES ('0070','2000700000001','Colis jeune bovin classique 10 kg','Colis jeune bovin classique 10 kg','Colis','viande_bovine',19,'barquette','froid_positif',1,700);
INSERT IGNORE INTO `produits` (`plu`,`ean13`,`libelle`,`libelle_court`,`famille`,`classe_traca`,`prix_kg`,`conditionnement`,`conservation`,`actif`,`ordre`) VALUES ('0071','2000710000008','Colis jeune bovin grillade 3 kg','Colis jeune bovin grillade 3 kg','Colis','viande_bovine',23,'barquette','froid_positif',1,710);
INSERT IGNORE INTO `produits` (`plu`,`ean13`,`libelle`,`libelle_court`,`famille`,`classe_traca`,`prix_kg`,`conditionnement`,`conservation`,`actif`,`ordre`) VALUES ('0072','2000720000005','Colis jeune bovin grillade 5 kg','Colis jeune bovin grillade 5 kg','Colis','viande_bovine',22,'barquette','froid_positif',1,720);
INSERT IGNORE INTO `produits` (`plu`,`ean13`,`libelle`,`libelle_court`,`famille`,`classe_traca`,`prix_kg`,`conditionnement`,`conservation`,`actif`,`ordre`) VALUES ('0073','2000730000002','Colis jeune bovin grillade 10 kg','Colis jeune bovin grillade 10 kg','Colis','viande_bovine',21,'barquette','froid_positif',1,730);
INSERT IGNORE INTO `produits` (`plu`,`ean13`,`libelle`,`libelle_court`,`famille`,`classe_traca`,`prix_kg`,`conditionnement`,`conservation`,`actif`,`ordre`) VALUES ('0074','2000740000009','Colis bœuf classique 5 kg','Colis bœuf classique 5 kg','Colis','viande_bovine',18,'barquette','froid_positif',1,740);
INSERT IGNORE INTO `produits` (`plu`,`ean13`,`libelle`,`libelle_court`,`famille`,`classe_traca`,`prix_kg`,`conditionnement`,`conservation`,`actif`,`ordre`) VALUES ('0075','2000750000006','Colis bœuf classique 10 kg','Colis bœuf classique 10 kg','Colis','viande_bovine',17,'barquette','froid_positif',1,750);
INSERT IGNORE INTO `produits` (`plu`,`ean13`,`libelle`,`libelle_court`,`famille`,`classe_traca`,`prix_kg`,`conditionnement`,`conservation`,`actif`,`ordre`) VALUES ('0076','2000760000003','Colis bœuf grillade 3 kg','Colis bœuf grillade 3 kg','Colis','viande_bovine',21,'barquette','froid_positif',1,760);
INSERT IGNORE INTO `produits` (`plu`,`ean13`,`libelle`,`libelle_court`,`famille`,`classe_traca`,`prix_kg`,`conditionnement`,`conservation`,`actif`,`ordre`) VALUES ('0077','2000770000000','Colis bœuf grillade 5 kg','Colis bœuf grillade 5 kg','Colis','viande_bovine',20,'barquette','froid_positif',1,770);
INSERT IGNORE INTO `produits` (`plu`,`ean13`,`libelle`,`libelle_court`,`famille`,`classe_traca`,`prix_kg`,`conditionnement`,`conservation`,`actif`,`ordre`) VALUES ('0078','2000780000007','Colis bœuf grillade 10 kg','Colis bœuf grillade 10 kg','Colis','viande_bovine',19,'barquette','froid_positif',1,780);

-- Marque bio les nouveaux morceaux (mono-ingredient, 100%)
INSERT INTO `recette_lignes` (`produit_id`,`libelle`,`quantite`,`unite`,`nature`,`bio`,`ordre`)
SELECT p.id, CASE WHEN p.famille='Colis' THEN 'Viande bovine' ELSE CONCAT('Viande de ', LOWER(p.famille)) END,
       1.000,'kg','agricole',1,10
FROM `produits` p
WHERE p.classe_traca='viande_bovine'
  AND NOT EXISTS (SELECT 1 FROM (SELECT produit_id FROM `recette_lignes`) x WHERE x.produit_id=p.id);

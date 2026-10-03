-- ============================================================
--  007 : lots ouverts / clôturés, étiquettes importées,
--        préférences par utilisateur.
--
--  Le boucher ouvre son lot AVANT de fabriquer : le numéro existe
--  dès cet instant, il peut étiqueter. Il clôture en fin de
--  fabrication, quand il connaît enfin le poids produit.
--
--  Volontairement, `quantite` n'est PAS modifiée. Son type exact
--  n'est pas connu du dépôt (le schéma de base précède les
--  migrations), et une colonne de registre réglementaire ne se
--  retouche pas à l'aveugle. C'est `statut` qui porte le sens :
--  tant qu'un lot est 'ouvert', sa quantité n'est pas une donnée,
--  et aucun écran ne l'affiche.
-- ============================================================

ALTER TABLE `lots_sortie` ADD COLUMN `statut` VARCHAR(12) NOT NULL DEFAULT 'cloture';
ALTER TABLE `lots_sortie` ADD COLUMN `ouvert_le` DATETIME DEFAULT NULL;
ALTER TABLE `lots_sortie` ADD COLUMN `ouvert_par` VARCHAR(80) DEFAULT NULL;
ALTER TABLE `lots_sortie` ADD COLUMN `cloture_le` DATETIME DEFAULT NULL;
ALTER TABLE `lots_sortie` ADD COLUMN `cloture_par` VARCHAR(80) DEFAULT NULL;
ALTER TABLE `lots_sortie` ADD KEY `idx_statut` (`statut`, `date_fabrication`);

-- Tout ce qui existe déjà est terminé : un registre ne contient que
-- des fabrications achevées.
UPDATE `lots_sortie` SET `statut` = 'cloture' WHERE `statut` <> 'ouvert';

-- ── Étiquettes remontées de la balance (parcours « saisie différée »)
CREATE TABLE IF NOT EXISTS `etiquettes_imports` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `source`         VARCHAR(20)  NOT NULL DEFAULT 'fichier',
  `fichier`        VARCHAR(255) NOT NULL,
  `lignes_lues`    INT NOT NULL DEFAULT 0,
  `importees`      INT NOT NULL DEFAULT 0,
  `doublons`       INT NOT NULL DEFAULT 0,
  `rattachees`     INT NOT NULL DEFAULT 0,
  `utilisateur_id` INT DEFAULT NULL,
  `importe_le`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `etiquettes` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `import_id`       INT DEFAULT NULL,
  `sortie_id`       INT DEFAULT NULL COMMENT 'lot de fabrication rattaché',
  `num_lot_fichier` VARCHAR(40)  DEFAULT NULL COMMENT 'numéro lu dans le fichier',
  `produit`         VARCHAR(150) DEFAULT NULL,
  `plu`             CHAR(4)      DEFAULT NULL,
  `date_etiquette`  DATE         DEFAULT NULL,
  `poids_kg`        DECIMAL(10,3) DEFAULT NULL,
  -- Empreinte de la ligne : réimporter le même fichier ne crée pas
  -- de doublon, mais deux étiquettes identiques restent deux pesées.
  `empreinte`       CHAR(64) NOT NULL,
  `cree_le`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_etiquettes_empreinte` (`empreinte`),
  KEY `idx_etiquettes_sortie` (`sortie_id`),
  KEY `idx_etiquettes_date` (`date_etiquette`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Préférences par utilisateur (ex. « ne plus me proposer
--    d'installer l'agent balance »).
CREATE TABLE IF NOT EXISTS `reglages_utilisateur` (
  `utilisateur_id` INT NOT NULL,
  `cle`            VARCHAR(50) NOT NULL,
  `valeur`         TEXT,
  PRIMARY KEY (`utilisateur_id`, `cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Inventaire de la base DFS remonté par l'agent balance.
INSERT IGNORE INTO `reglages` (`cle`, `valeur`) VALUES ('agent_inventaire', '');

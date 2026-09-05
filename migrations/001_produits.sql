-- ============================================================
--  Referentiel produits : source de verite pour l'appli ET pour la balance
--  PLU 4 chiffres + EAN-13 interne (prefixe 2) exiges par DFS / DGI
-- ============================================================
CREATE TABLE IF NOT EXISTS `produits` (
  `id`              INT(11)      NOT NULL AUTO_INCREMENT,
  `plu`             CHAR(4)      NOT NULL COMMENT '4 chiffres, impose par DFS',
  `ean13`           CHAR(13)     DEFAULT NULL COMMENT 'interne, prefixe 2',
  `libelle`         VARCHAR(150) NOT NULL,
  `libelle_court`   VARCHAR(40)  DEFAULT NULL COMMENT 'tenant sur l etiquette',
  `famille`         VARCHAR(60)  DEFAULT NULL,
  `classe_traca`    VARCHAR(60)  DEFAULT NULL COMMENT 'classe de tracabilite DFS',
  `prix_kg`         DECIMAL(8,2) DEFAULT NULL,
  `dlc_jours`       INT(11)      DEFAULT NULL COMMENT 'duree de vie, pour calculer la DLC',
  `conditionnement` ENUM('barquette','sous_vide','vrac') DEFAULT NULL,
  `conservation`    ENUM('froid_positif','congele')      DEFAULT NULL,
  `actif`           TINYINT(1)   NOT NULL DEFAULT 1,
  `ordre`           INT(11)      NOT NULL DEFAULT 100,
  `date_creation`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plu` (`plu`),
  KEY `idx_actif` (`actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rattachement des fabrications au referentiel. Le libelle reste stocke en clair
-- dans lots_sortie.produit : un registre doit rester lisible meme si le produit
-- est renomme ou supprime plus tard.
ALTER TABLE `lots_sortie` ADD COLUMN `produit_id` INT(11) DEFAULT NULL AFTER `produit`;
ALTER TABLE `lots_sortie` ADD KEY `idx_produit_id` (`produit_id`);

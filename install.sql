-- ============================================================
--  TraçaBoucher v2 – Schéma complet
-- ============================================================
SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

CREATE TABLE IF NOT EXISTS `especes` (
  `id`      INT(11)     NOT NULL AUTO_INCREMENT,
  `code`    VARCHAR(3)  NOT NULL,
  `libelle` VARCHAR(50) NOT NULL,
  `emoji`   VARCHAR(5)  DEFAULT '🐄',
  `couleur` VARCHAR(7)  NOT NULL DEFAULT '#7A1C1C',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `especes` (`code`,`libelle`,`emoji`,`couleur`) VALUES
  ('AGN','Agneau','🐑','#5C7A3E'),
  ('VEA','Veau',  '🐮','#D4956A'),
  ('BOE','Bœuf',  '🐄','#7A1C1C'),
  ('POR','Porc',  '🐷','#C06A8A');

CREATE TABLE IF NOT EXISTS `lots_carcasses` (
  `id`                INT(11)      NOT NULL AUTO_INCREMENT,
  `num_lot`           VARCHAR(20)  NOT NULL,
  `espece_id`         INT(11)      NOT NULL,
  `date_entree`       DATE         NOT NULL,
  `nb_carcasses`      INT(11)      NOT NULL DEFAULT 1,
  `poids_carcasse_kg` DECIMAL(8,2) NOT NULL,
  `temperature`       DECIMAL(4,1) DEFAULT NULL,
  `qualite`           VARCHAR(20)  DEFAULT NULL,
  `tracabilite_status` VARCHAR(50) DEFAULT NULL,
  `fournisseur`       VARCHAR(100) DEFAULT NULL,
  `origine`           VARCHAR(100) DEFAULT 'France',
  `num_abattoir`      VARCHAR(50)  DEFAULT NULL,
  `doc_abattoir`      VARCHAR(255) DEFAULT NULL,
  `notes`             TEXT         DEFAULT NULL,
  `date_creation`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_num_lot` (`num_lot`),
  KEY `idx_espece`      (`espece_id`),
  KEY `idx_date_entree` (`date_entree`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sorties_viande` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `lot_id`       INT(11)      NOT NULL,
  `date_sortie`  DATE         NOT NULL,
  `type_sortie`  ENUM('vente_directe','traiteur','autre') NOT NULL DEFAULT 'vente_directe',
  `client`       VARCHAR(100) DEFAULT NULL,
  `poids_kg`     DECIMAL(8,2) NOT NULL,
  `prix_kg`      DECIMAL(8,2) DEFAULT NULL,
  `notes`        TEXT         DEFAULT NULL,
  `date_creation` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lot`        (`lot_id`),
  KEY `idx_date_sortie`(`date_sortie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ingredients` (
  `id`        INT(11)      NOT NULL AUTO_INCREMENT,
  `nom`       VARCHAR(100) NOT NULL,
  `categorie` VARCHAR(50)  DEFAULT NULL,
  `unite`     VARCHAR(10)  NOT NULL DEFAULT 'kg',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `ingredients` (`nom`,`categorie`,`unite`) VALUES
  ('Riz',               'Féculents',    'kg'),
  ('Semoule fine',      'Féculents',    'kg'),
  ('Semoule moyenne',   'Féculents',    'kg'),
  ('Semoule grosse',    'Féculents',    'kg'),
  ('Couscous',          'Féculents',    'kg'),
  ('Boulgour',          'Féculents',    'kg'),
  ('Lentilles',         'Légumineuses', 'kg'),
  ('Pois chiches',      'Légumineuses', 'kg'),
  ('Sel',               'Épices',       'kg'),
  ('Poivre',            'Épices',       'g'),
  ('Cumin',             'Épices',       'g'),
  ('Coriandre',         'Épices',       'g'),
  ('Ras-el-hanout',     'Épices',       'g'),
  ('Harissa',           'Condiments',   'kg'),
  ('Huile d\'olive',    'Huiles',       'L'),
  ('Huile de tournesol','Huiles',       'L'),
  ('Tomates concassées','Conserves',    'kg'),
  ('Concentré tomate',  'Conserves',    'kg'),
  ('Oignons',           'Légumes',      'kg'),
  ('Ail',               'Légumes',      'kg'),
  ('Carottes',          'Légumes',      'kg');

CREATE TABLE IF NOT EXISTS `achats_ingredients` (
  `id`            INT(11)       NOT NULL AUTO_INCREMENT,
  `num_facture`   VARCHAR(50)   DEFAULT NULL,
  `date_achat`    DATE          NOT NULL,
  `fournisseur`   VARCHAR(100)  DEFAULT NULL,
  `montant_total` DECIMAL(10,2) DEFAULT NULL,
  `doc_facture`   VARCHAR(255)  DEFAULT NULL,
  `notes`         TEXT          DEFAULT NULL,
  `date_creation` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_date_achat` (`date_achat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `achats_lignes` (
  `id`            INT(11)       NOT NULL AUTO_INCREMENT,
  `achat_id`      INT(11)       NOT NULL,
  `ingredient_id` INT(11)       NOT NULL,
  `ref_lot_ing`   VARCHAR(30)   DEFAULT NULL COMMENT 'N° lot ingrédient ex: SEM-001',
  `quantite`      DECIMAL(10,3) NOT NULL,
  `prix_unitaire` DECIMAL(8,3)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_achat`      (`achat_id`),
  KEY `idx_ingredient` (`ingredient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `plats_cuisines` (
  `id`               INT(11)      NOT NULL AUTO_INCREMENT,
  `ref_lot_fini`     VARCHAR(20)  DEFAULT NULL COMMENT 'TR-YYYYMMDD-XXX auto',
  `nom`              VARCHAR(100) NOT NULL,
  `date_preparation` DATE         NOT NULL,
  `quantite_kg`      DECIMAL(8,2) DEFAULT NULL,
  `nb_portions`      INT(11)      DEFAULT NULL,
  `conditionnement`  ENUM('mise_sous_vide','barquette','vrac') DEFAULT 'vrac',
  `notes`            TEXT         DEFAULT NULL,
  `date_creation`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_date_prep` (`date_preparation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `plats_viande` (
  `id`       INT(11)      NOT NULL AUTO_INCREMENT,
  `plat_id`  INT(11)      NOT NULL,
  `lot_id`   INT(11)      NOT NULL,
  `poids_kg` DECIMAL(8,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_plat` (`plat_id`),
  KEY `idx_lot`  (`lot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `plats_ingredients` (
  `id`                INT(11)       NOT NULL AUTO_INCREMENT,
  `plat_id`           INT(11)       NOT NULL,
  `achat_ligne_id`    INT(11)       NOT NULL,
  `quantite_utilisee` DECIMAL(10,3) NOT NULL,
  `unite_affichee`    VARCHAR(10)   DEFAULT 'kg',
  PRIMARY KEY (`id`),
  KEY `idx_plat` (`plat_id`),
  KEY `idx_ligne`(`achat_ligne_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `lots_carcasses`
  ADD CONSTRAINT `fk_lot_espece` FOREIGN KEY (`espece_id`) REFERENCES `especes` (`id`);

ALTER TABLE `sorties_viande`
  ADD CONSTRAINT `fk_sortie_lot` FOREIGN KEY (`lot_id`) REFERENCES `lots_carcasses` (`id`) ON DELETE CASCADE;

ALTER TABLE `achats_lignes`
  ADD CONSTRAINT `fk_ligne_achat` FOREIGN KEY (`achat_id`) REFERENCES `achats_ingredients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ligne_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`);

ALTER TABLE `plats_viande`
  ADD CONSTRAINT `fk_pv_plat` FOREIGN KEY (`plat_id`) REFERENCES `plats_cuisines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pv_lot`  FOREIGN KEY (`lot_id`) REFERENCES `lots_carcasses` (`id`);

ALTER TABLE `plats_ingredients`
  ADD CONSTRAINT `fk_pi_plat`  FOREIGN KEY (`plat_id`) REFERENCES `plats_cuisines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pi_ligne` FOREIGN KEY (`achat_ligne_id`) REFERENCES `achats_lignes` (`id`);

SET foreign_key_checks = 1;

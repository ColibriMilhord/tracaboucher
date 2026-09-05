-- ============================================================
--  Composition des produits : justifie le taux bio (RUE 2018/848)
--  Le ratio porte sur les seuls ingredients AGRICOLES ;
--  l'eau et le sel en sont exclus par le reglement.
--
--  La cle etrangere est declaree DANS le CREATE TABLE et sans nom :
--  MariaDB lui attribue un nom unique. Un ALTER ... ADD CONSTRAINT nomme
--  echouerait a la deuxieme execution (erreur 1005 / errno 121), les noms
--  de contrainte etant uniques a l'echelle de la base entiere.
-- ============================================================
CREATE TABLE IF NOT EXISTS `recette_lignes` (
  `id`         INT(11)       NOT NULL AUTO_INCREMENT,
  `produit_id` INT(11)       NOT NULL,
  `libelle`    VARCHAR(120)  NOT NULL,
  `quantite`   DECIMAL(10,3) NOT NULL COMMENT 'pour une fabrication type',
  `unite`      VARCHAR(10)   NOT NULL DEFAULT 'kg',
  `nature`     ENUM('agricole','eau','sel','autre') NOT NULL DEFAULT 'agricole',
  `bio`        TINYINT(1)    NOT NULL DEFAULT 0,
  `ordre`      INT(11)       NOT NULL DEFAULT 100,
  PRIMARY KEY (`id`),
  KEY `idx_produit` (`produit_id`),
  FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rattrapage : si une execution precedente s'est interrompue, la table peut
-- exister sans toutes ses colonnes. Chaque ajout est sans effet si la colonne
-- est deja la (erreur 1060, toleree par l'installateur).
ALTER TABLE `recette_lignes` ADD COLUMN `unite`  VARCHAR(10) NOT NULL DEFAULT 'kg' AFTER `quantite`;
ALTER TABLE `recette_lignes` ADD COLUMN `nature` ENUM('agricole','eau','sel','autre') NOT NULL DEFAULT 'agricole' AFTER `unite`;
ALTER TABLE `recette_lignes` ADD COLUMN `bio`    TINYINT(1)  NOT NULL DEFAULT 0 AFTER `nature`;
ALTER TABLE `recette_lignes` ADD COLUMN `ordre`  INT(11)     NOT NULL DEFAULT 100 AFTER `bio`;

INSERT IGNORE INTO `reglages` (`cle`,`valeur`) VALUES
  ('code_certificateur', ''),
  ('origine_agricole',   'Agriculture France');

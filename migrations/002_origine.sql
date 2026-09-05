-- ============================================================
--  Mentions d'origine (reglement CE 1760/2000)
--  Elevage en propre : les pays sont constants, seuls les lots
--  atypiques derogent aux valeurs par defaut.
-- ============================================================
ALTER TABLE `lots_entree` ADD COLUMN `num_animal`        VARCHAR(30) DEFAULT NULL AFTER `num_lot_fournisseur`;
ALTER TABLE `lots_entree` ADD COLUMN `pays_naissance`    VARCHAR(60) DEFAULT NULL AFTER `num_animal`;
ALTER TABLE `lots_entree` ADD COLUMN `pays_elevage`      VARCHAR(60) DEFAULT NULL AFTER `pays_naissance`;
ALTER TABLE `lots_entree` ADD COLUMN `pays_abattage`     VARCHAR(60) DEFAULT NULL AFTER `pays_elevage`;
ALTER TABLE `lots_entree` ADD COLUMN `agrement_abattoir` VARCHAR(30) DEFAULT NULL AFTER `pays_abattage`;

INSERT IGNORE INTO `reglages` (`cle`,`valeur`) VALUES
  ('origine_naissance',  'France'),
  ('origine_elevage',    'France'),
  ('origine_abattage',   'France'),
  ('agrement_abattoir',  ''),
  ('pays_decoupe',       'France'),
  ('agrement_atelier',   ''),
  ('elevage_en_propre',  '1');

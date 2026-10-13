-- ============================================================
--  008 : une fabrication peut en utiliser d'autres.
--
--  Exemple : une sauce marinade (lot de fabrication) entre dans la
--  composition d'escalopes marinées (autre lot de fabrication).
--  La chaîne se remonte et se descend dans les deux sens : escalope,
--  sauce, puis matières premières de la sauce.
--
--  Table à part de lots_sortie_entrees : celle-ci reste inchangée,
--  c'est le chemin de la grande majorité des fabrications.
--  Pas de clé étrangère, le type exact de lots_sortie.id n'étant pas
--  connu du dépôt. Le code supprime les liens avec la fabrication.
-- ============================================================

CREATE TABLE IF NOT EXISTS `lots_sortie_sources` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `sortie_id`        INT NOT NULL COMMENT 'la fabrication qui utilise',
  `source_sortie_id` INT NOT NULL COMMENT 'la fabrication utilisée',
  `quantite_kg`      DECIMAL(10,3) DEFAULT NULL,
  UNIQUE KEY `uk_sortie_source` (`sortie_id`, `source_sortie_id`),
  KEY `idx_source` (`source_sortie_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

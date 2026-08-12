-- 0029_vhost_aliases.sql
-- Alias de dominio (Apache ServerAlias): lista separada por espacios de nombres de host extra
-- que sirven el MISMO sitio que el dominio. Editable desde el módulo Domains (vista AliasSettings);
-- el hook OnDaemonRun de apache_admin los añade al ServerAlias del vhost (HTTP y SSL) validándolos.
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'x_vhosts' AND COLUMN_NAME = 'vh_aliases_vc');
SET @sql := IF(@col = 0,
    'ALTER TABLE `x_vhosts` ADD COLUMN `vh_aliases_vc` varchar(500) DEFAULT NULL COMMENT ''Alias (ServerAlias) del dominio, separados por espacios'' AFTER `vh_name_vc`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 0023_vhost_le_extra_sans.sql
-- SAN extra por dominio del usuario para su certificado Let's Encrypt (subdominios propios).
-- Lista CSV editable desde la UI de Sencrypt; el hook de emisión la añade validando que cada
-- nombre sea subdominio del propio dominio (anti-abuso) y emite por DNS-01.
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'x_vhosts' AND COLUMN_NAME = 'vh_le_extra_sans');
SET @sql := IF(@col = 0,
    'ALTER TABLE `x_vhosts` ADD COLUMN `vh_le_extra_sans` TEXT DEFAULT NULL AFTER `vh_ssl_port_in`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

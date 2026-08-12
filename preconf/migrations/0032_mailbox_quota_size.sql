-- 0032: tamaño de buzón por cuenta (mb_quota_in, en MB) descontado de la cuota de disco del paquete.
-- Antes todos los buzones recibían la cuota global max_mail_size (200 MB) sin intervención del
-- usuario. Ahora el usuario elige el tamaño al crear/editar cada buzón (por defecto max_mail_size,
-- tope por buzón = max_mail_size) y la suma de tamaños se descuenta de la cuota de disco del
-- paquete (qt_diskspace_bi; 0 = ilimitado). Buzones existentes conservan la cuota efectiva que ya
-- tenían aplicada en quota2 (max_mail_size).
SET @db = DATABASE();

SET @tiene = (SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'x_mailboxes' AND COLUMN_NAME = 'mb_quota_in');
SET @sql = IF(@tiene = 0,
    'ALTER TABLE x_mailboxes ADD COLUMN mb_quota_in INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''Tamaño del buzón en MB (0 = usar max_mail_size)''',
    'SELECT ''mb_quota_in ya existe''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE x_mailboxes SET mb_quota_in = CAST((SELECT so_value_tx FROM x_settings WHERE so_name_vc = 'max_mail_size') AS UNSIGNED)
WHERE mb_quota_in = 0;

-- Aclarar la descripción del ajuste: ahora es el valor POR DEFECTO y el tope por buzón.
UPDATE x_settings
SET so_desc_tx = 'Default mailbox size (MB) when creating a mailbox, and the maximum size a single mailbox can have. Users can choose any size up to this value, discounted from their package disk quota. Default = 200'
WHERE so_name_vc = 'max_mail_size';
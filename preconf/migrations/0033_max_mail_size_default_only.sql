-- 0033: max_mail_size ya NO es tope por buzón, solo el valor POR DEFECTO del campo
-- "Mailbox size (MB)" al crear un buzón. El usuario puede elegir cualquier tamaño
-- (límite de cordura 10 TB); el límite real es la cuota de disco del paquete.
UPDATE x_settings
SET so_desc_tx = 'Default mailbox size (MB) pre-filled when creating a mailbox. Users can freely choose a different size per mailbox (limited only by their package disk quota). Default = 200'
WHERE so_name_vc = 'max_mail_size';
-- 0028_mailserver_admin_module.sql
-- Nuevo módulo admin "Mail Server" (folder mailserver_admin): vista SOLO LECTURA del servidor de
-- correo (cola, log reciente, contadores, IPs bloqueadas). Categoría 6 = Mail, tipo modadmin (admin).
INSERT INTO `x_modules` (`mo_category_fk`, `mo_name_vc`, `mo_version_in`, `mo_folder_vc`, `mo_type_en`, `mo_desc_tx`, `mo_installed_ts`, `mo_enabled_en`)
SELECT 6, 'Mail Server', 100, 'mailserver_admin', 'modadmin',
       'Estado del servidor de correo: cola, log reciente, contadores e IPs bloqueadas (solo lectura).',
       UNIX_TIMESTAMP(), 'true'
WHERE NOT EXISTS (SELECT 1 FROM `x_modules` WHERE `mo_folder_vc` = 'mailserver_admin');

-- Permiso para el grupo admin (1). En instalaciones nuevas lo cubre el SELECT '1', mo_id_pk de
-- x_permissions; en las existentes ese SELECT ya se ejecutó antes de existir el módulo -> añadir aquí.
INSERT INTO `x_permissions` (`pe_group_fk`, `pe_module_fk`)
  SELECT 1, m.`mo_id_pk` FROM `x_modules` m
   WHERE m.`mo_folder_vc` = 'mailserver_admin'
     AND NOT EXISTS (SELECT 1 FROM `x_permissions` p WHERE p.`pe_group_fk`=1 AND p.`pe_module_fk`=m.`mo_id_pk`);

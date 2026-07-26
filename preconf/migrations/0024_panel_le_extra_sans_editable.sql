-- 0024_panel_le_extra_sans_editable.sql
-- El editor de los SANs de servicio del cert del panel pasa del módulo Sencrypt al módulo
-- "Bulwark Config" (es config de sistema). Basta marcar el setting como editable por el usuario
-- (admin) para que aparezca en la página de configuración, con nombre y descripción claros.
UPDATE `x_settings`
   SET `so_usereditable_en` = 'true',
       `so_cleanname_vc` = 'Certificado del panel: nombres de servicio (SANs)',
       `so_desc_tx` = 'Lista (separada por comas) de nombres de servicio a incluir en el certificado del panel, p.ej. mail.tudominio, ftp.tudominio. El FQDN del panel y los subdominios cableados al cert del panel (mta-sts) se añaden solos. Se siembra al instalar con el MX real. Solo se incluyen los nombres bajo una zona que gestiona el panel (validación DNS-01); se aplican en la próxima renovación.'
 WHERE `so_name_vc` = 'panel_le_extra_sans';

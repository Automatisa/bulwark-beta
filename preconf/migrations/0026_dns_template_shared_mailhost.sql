-- 0026_dns_template_shared_mailhost.sql
-- La plantilla de zona ponía el MX como mail.<dominio> (mail.:DOMAIN:), que NO está en el certificado
-- del panel -> el MX de cada cliente no coincidía con el cert (fallo X509/DANE/MTA-STS). Ahora el MX
-- usa :MAILHOST: = host de correo COMPARTIDO (el MX del dominio proveedor, cubierto por el cert).
UPDATE `x_dns_create`
   SET `dc_target_vc` = ':MAILHOST:'
 WHERE `dc_acc_fk` = 0 AND `dc_type_vc` = 'MX' AND `dc_host_vc` = '@' AND `dc_target_vc` = 'mail.:DOMAIN:';

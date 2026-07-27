-- 0027_dmarc_default_quarantine.sql
-- El DMARC por defecto de los dominios nuevos pasa de p=none (solo monitoriza) a p=quarantine
-- (el correo que falla DMARC va a Spam). Seguro en este panel porque genera y alinea DKIM+SPF por
-- dominio, así que el correo legítimo pasa. Más adelante puede subirse a p=reject.
UPDATE `x_dns_create`
   SET `dc_target_vc` = REPLACE(`dc_target_vc`, 'p=none', 'p=quarantine')
 WHERE `dc_acc_fk` = 0 AND `dc_type_vc` = 'TXT' AND `dc_host_vc` = '_dmarc' AND `dc_target_vc` LIKE '%p=none%';

-- Dominios ya existentes con el DMARC por defecto (p=none) -> quarantine. No toca los personalizados.
UPDATE `x_dns` d
   JOIN `x_vhosts` v ON v.vh_id_pk = d.dn_vhost_fk
   SET d.dn_target_vc = REPLACE(d.dn_target_vc, 'p=none', 'p=quarantine')
 WHERE d.dn_type_vc = 'TXT' AND d.dn_host_vc = '_dmarc' AND d.dn_deleted_ts IS NULL
   AND d.dn_target_vc LIKE '%p=none%' AND v.vh_deleted_ts IS NULL;

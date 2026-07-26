-- 0022_panel_le_extra_sans.sql
-- SANs adicionales del certificado Let's Encrypt del panel (multi-SAN por DNS-01).
-- Lista separada por comas de hostnames extra (p.ej. mail.<dominio>,ftp.<dominio>,mta-sts.<dominio>).
-- Solo se añaden los que caen bajo una zona gestionada por el panel. Vacío = solo el FQDN del panel.
INSERT INTO `x_settings` (`so_name_vc`, `so_cleanname_vc`, `so_value_tx`, `so_defvalues_tx`, `so_desc_tx`, `so_module_vc`, `so_usereditable_en`)
SELECT 'panel_le_extra_sans', 'SANs extra del cert del panel', '', NULL,
       'Lista separada por comas de hostnames adicionales a incluir en el certificado Let''s Encrypt del panel (p.ej. mail.<dominio>,ftp.<dominio>,mta-sts.<dominio>). Solo se añaden los que caen bajo una zona que gestiona el panel; se validan por DNS-01.',
       'Bulwark Config', 'false'
WHERE NOT EXISTS (SELECT 1 FROM `x_settings` WHERE `so_name_vc` = 'panel_le_extra_sans');

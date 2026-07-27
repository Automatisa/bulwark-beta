-- 0025_panel_le_extra_sans_own_block.sql
-- El setting panel_le_extra_sans deja de mostrarse en la lista genérica de ajustes (se veía mal como
-- textarea suelto) y pasa a editarse en su propio bloque "Certificado del panel" dentro de la sección
-- "Dominio del panel" de Bulwark Config (con su botón). Basta marcarlo como NO editable en la lista.
UPDATE `x_settings` SET `so_usereditable_en` = 'false' WHERE `so_name_vc` = 'panel_le_extra_sans';

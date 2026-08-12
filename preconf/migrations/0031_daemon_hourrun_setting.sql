-- 0031: sembrar la opción daemon_hourrun si no existe.
-- daemon.php la usa como compuerta del ciclo horario (OnDaemonHour), pero el instalador
-- nunca la sembró (solo lastrun/dayrun/weekrun/monthrun). Si falta, GetSystemOption
-- devuelve false y SetSystemOption(create=false) solo hace UPDATE -> jamás se crea:
-- la compuerta (time() >= false + 3600) pasa en CADA tick del cron y los hooks horarios
-- (renovación SSL de sencrypt, estadísticas webalizer) se ejecutan cada 5 minutos.
INSERT INTO x_settings (so_name_vc, so_cleanname_vc, so_value_tx, so_defvalues_tx, so_desc_tx, so_module_vc, so_usereditable_en)
SELECT 'daemon_hourrun', 'Daemon timeing cache', '0', NULL, 'Timestamp of when the daemon hourly cycle last ran.', NULL, 'false'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM x_settings WHERE so_name_vc = 'daemon_hourrun');

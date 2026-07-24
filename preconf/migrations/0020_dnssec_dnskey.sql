-- 0020_dnssec_dnskey.sql — guarda la DNSKEY pública (KSK) y el algoritmo en x_dns_dnssec, para
-- que el panel pueda mostrar tanto el registro DS como la DNSKEY (registradores como OVH piden
-- la DNSKEY: flag 257 + algoritmo + clave pública base64, y calculan el DS ellos mismos).
-- Idempotente: cada ALTER en su propia sentencia; si la columna ya existe, db_migrate registra la
-- migración igualmente (las reinstalaciones traen el esquema al día vía bulwark_core.sql).
ALTER TABLE `x_dns_dnssec` ADD COLUMN `dd_dnskey_tx` text DEFAULT NULL;
ALTER TABLE `x_dns_dnssec` ADD COLUMN `dd_algo_in` int(11) DEFAULT NULL;

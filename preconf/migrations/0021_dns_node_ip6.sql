-- 0021_dns_node_ip6.sql — añade la IPv6 pública del nodo del cluster DNS (x_dns_nodes.nd_ip6_vc),
-- para que al unir un segundo nodo se creen también sus registros AAAA (ns/panel) y los
-- nameservers del cluster queden en doble pila (IPv4 + IPv6). NULL = nodo solo IPv4.
-- Idempotente: si la columna ya existe, db_migrate registra la migración igualmente (las
-- reinstalaciones traen el esquema al día vía bulwark_core.sql).
ALTER TABLE `x_dns_nodes` ADD COLUMN `nd_ip6_vc` varchar(45) DEFAULT NULL COMMENT 'IPv6 publica del nodo (AAAA de ns/panel); NULL = solo IPv4';

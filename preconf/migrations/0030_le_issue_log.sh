#!/bin/sh
# 0030_le_issue_log.sh — Historial de emisiones de certificados Let's Encrypt (una fila por evento
# de emisión). Alimenta el cooldown por historial de sencrypt: el límite REAL de LE es "5 certs por
# set EXACTO de identificadores / 7 días", así que el panel cuenta cuántos certs emitió para cada
# set en esa ventana y solo programa espera cuando el cupo está agotado (antes: guarda plana de 48h).
# El daemon (hook OnDaemonHour) inserta un evento tras cada signDomains correcto y siembra una fila
# por vhost con el cert vigente. Idempotente.

DBPHP=/usr/local/bulwark/cnf/db.php
U=$(php -r "include \"$DBPHP\"; echo \$user;")
P=$(php -r "include \"$DBPHP\"; echo \$pass;")
H=$(php -r "include \"$DBPHP\"; echo \$host;")
DB=$(php -r "include \"$DBPHP\"; echo \$dbname;")
MY="mysql -u$U -p$P -h$H -N"

HAS=$($MY -e "SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA='$DB' AND TABLE_NAME='x_le_issue_log'" 2>/dev/null)

if [ "$HAS" = "0" ]; then
    $MY -e "CREATE TABLE \`$DB\`.\`x_le_issue_log\` (
              \`li_id_pk\` int(10) unsigned NOT NULL AUTO_INCREMENT,
              \`li_vhost_fk\` int(10) NOT NULL DEFAULT 0,
              \`li_domain_vc\` varchar(255) NOT NULL,
              \`li_set_hash_vc\` char(32) NOT NULL,
              \`li_set_tx\` text NOT NULL,
              \`li_issued_ts\` int(20) NOT NULL,
              \`li_env_vc\` varchar(16) DEFAULT 'production',
              PRIMARY KEY (\`li_id_pk\`),
              KEY \`li_set_ts\` (\`li_set_hash_vc\`, \`li_issued_ts\`),
              KEY \`li_vhost\` (\`li_vhost_fk\`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8" 2>/dev/null
    echo "x_le_issue_log creada"
else
    echo "x_le_issue_log ya existe"
fi
exit 0
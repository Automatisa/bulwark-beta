#!/bin/sh
# 0039_webmail_https_certs.sh — HTTPS sin aviso de navegador en webmail.<dominio>.
#
# Desde esta versión:
#   - sencrypt añade webmail.<dominio> POR DEFECTO a los certs de dominios raíz cuya zona está
#     delegada públicamente al panel (validación DNS-01; con DNS externo el cert no cambia), y el
#     hook horario reemite automáticamente los certs vigentes que aún no cubren webmail.<dominio>
#     (ni por SAN ni por wildcard *.dominio).
#   - apache_admin emite un vhost EXACTO webmail.<dominio>:443 con el cert del propio dominio
#     cuando éste cubre el nombre; el vhost comodín webmail.* (cert del panel) queda como fallback
#     para dominios sin cert. Un vhost webmail.<dominio> creado por el cliente tiene prioridad.
#
# Esta migración solo marca apache_changed=true para regenerar httpd-vhosts.conf cuanto antes:
# los dominios cuyo cert YA cubre webmail (p.ej. los que tienen wildcard) estrenan vhost exacto en
# el próximo ciclo del daemon sin esperar a otro disparador. Las reemisiones de los certs que aún
# no lo cubren las programa el propio hook horario de sencrypt. Idempotente.

DBPHP=/usr/local/bulwark/cnf/db.php
[ -f "$DBPHP" ] || { echo "no existe $DBPHP; nada que hacer"; exit 0; }
U=$(php -r "include \"$DBPHP\"; echo \$user;")
P=$(php -r "include \"$DBPHP\"; echo \$pass;")
H=$(php -r "include \"$DBPHP\"; echo \$host;")
DB=$(php -r "include \"$DBPHP\"; echo \$dbname;")

MY="mysql -u$U -p$P -h$H -N"

$MY -e "UPDATE \`$DB\`.x_settings SET so_value_tx='true' WHERE so_name_vc='apache_changed'" 2>/dev/null
echo "apache_changed=true: el daemon regenerara httpd-vhosts.conf con los vhosts exactos webmail.<dominio>"
exit 0

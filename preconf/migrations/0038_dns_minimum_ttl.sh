#!/bin/sh
# 0038_dns_minimum_ttl.sh — SOA "minimum TTL" 86400 -> 3600 y reescritura de zonas.
# El campo "minimum" de la SOA gobierna la CACHE NEGATIVA (RFC 2308): si un registro no
# existe cuando un resolver pregunta, el NXDOMAIN se cachea durante ese minimo. Con 86400 s
# (24 h) cualquier registro nuevo tardaba hasta un dia en ser visible en resolvers publicos
# que hubieran visto el NXDOMAIN. Se baja a 3600 s (1 h), recomendacion habitual, y se marcan
# todas las zonas para reescritura con la nueva SOA.
# Solo toca el ajuste si sigue valiendo el default heredado (86400), para no pisar una
# personalizacion manual hecha desde el panel. Idempotente.

DBPHP=/usr/local/bulwark/cnf/db.php
[ -f "$DBPHP" ] || { echo "no existe $DBPHP; nada que hacer"; exit 0; }
U=$(php -r "include \"$DBPHP\"; echo \$user;")
P=$(php -r "include \"$DBPHP\"; echo \$pass;")
H=$(php -r "include \"$DBPHP\"; echo \$host;")
DB=$(php -r "include \"$DBPHP\"; echo \$dbname;")

MY="mysql -u$U -p$P -h$H -N"

# 1) minimum_ttl 86400 -> 3600 (solo si es el default heredado)
CUR=$($MY -e "SELECT so_value_tx FROM \`$DB\`.x_settings WHERE so_name_vc='minimum_ttl'" 2>/dev/null | tr -d '[:space:]')
if [ "$CUR" = "86400" ]; then
    $MY -e "UPDATE \`$DB\`.x_settings SET so_value_tx='3600' WHERE so_name_vc='minimum_ttl'" 2>/dev/null
    echo "minimum_ttl: 86400 -> 3600 (cache negativa DNS limitada a 1 h)"
else
    echo "minimum_ttl=$CUR: no es el default heredado; se respeta"
fi

# 2) Marcar TODAS las zonas activas para reescritura con la nueva SOA
IDS=$($MY -e "SELECT GROUP_CONCAT(vh_id_pk) FROM \`$DB\`.x_vhosts
              WHERE vh_type_in = 1 AND vh_deleted_ts IS NULL" 2>/dev/null)
if [ -n "$IDS" ] && [ "$IDS" != "NULL" ]; then
    $MY -e "UPDATE \`$DB\`.x_settings SET so_value_tx='$IDS' WHERE so_name_vc='dns_hasupdates'" 2>/dev/null
    echo "dns_hasupdates: reescritura programada para las zonas: $IDS"
else
    echo "sin zonas activas; nada que reescribir"
fi
exit 0

#!/bin/sh
# 0037_webmail_subdomain_redirect.sh — soporte para webmail.<dominio> -> webmail del servidor.
# El daemon apache_admin emite unos vhosts comodín (ServerAlias webmail.*) que redirigen
# al webmail del panel; esta migración prepara el DNS:
#   1) Plantilla x_dns_create: los dominios NUEVOS reciben un registro A "webmail" -> server_ip.
#   2) Backfill: añade ese registro A "webmail" a las zonas existentes que no lo tengan y
#      marca dns_hasupdates para que el daemon DNS las reescriba.
#   3) Marca apache_changed=true para que el daemon regenere httpd-vhosts.conf con los
#      vhosts de redirección.
# Idempotente.

DBPHP=/usr/local/bulwark/cnf/db.php
[ -f "$DBPHP" ] || { echo "no existe $DBPHP; nada que hacer"; exit 0; }
U=$(php -r "include \"$DBPHP\"; echo \$user;")
P=$(php -r "include \"$DBPHP\"; echo \$pass;")
H=$(php -r "include \"$DBPHP\"; echo \$host;")
DB=$(php -r "include \"$DBPHP\"; echo \$dbname;")

MY="mysql -u$U -p$P -h$H -N"

# 1) Plantilla para dominios nuevos
TPL=$($MY -e "SELECT COUNT(*) FROM \`$DB\`.x_dns_create
              WHERE dc_acc_fk=0 AND dc_type_vc='A' AND dc_host_vc='webmail'" 2>/dev/null)
if [ "$TPL" = "0" ]; then
    $MY -e "INSERT INTO \`$DB\`.x_dns_create
            (dc_acc_fk, dc_type_vc, dc_host_vc, dc_ttl_in, dc_target_vc)
            VALUES (0, 'A', 'webmail', 3600, ':IP:')" 2>/dev/null
    echo "plantilla x_dns_create: anadido registro A webmail -> :IP:"
else
    echo "plantilla x_dns_create: registro A webmail ya existe"
fi

# 2) Backfill en zonas existentes
IP=$($MY -e "SELECT so_value_tx FROM \`$DB\`.x_settings WHERE so_name_vc='server_ip'" 2>/dev/null | tr -d '[:space:]')
if [ -z "$IP" ]; then
    echo "sin server_ip en x_settings; backfill DNS omitido"
else
    CHANGED=""
    for ROW in $($MY -e "SELECT CONCAT(v.vh_id_pk, '|', v.vh_acc_fk, '|', v.vh_name_vc)
                         FROM \`$DB\`.x_vhosts v
                         WHERE v.vh_type_in = 1 AND v.vh_deleted_ts IS NULL
                           AND NOT EXISTS (
                               SELECT 1 FROM \`$DB\`.x_dns d
                               WHERE d.dn_vhost_fk = v.vh_id_pk
                                 AND d.dn_deleted_ts IS NULL
                                 AND d.dn_type_vc = 'A' AND d.dn_host_vc = 'webmail'
                           )" 2>/dev/null); do
        ID=${ROW%%|*}
        REST=${ROW#*|}
        ACC=${REST%%|*}
        NAME=${REST#*|}
        $MY -e "INSERT INTO \`$DB\`.x_dns
                (dn_acc_fk, dn_name_vc, dn_vhost_fk, dn_type_vc, dn_host_vc, dn_ttl_in, dn_target_vc, dn_created_ts)
                VALUES ($ACC, '$NAME', $ID, 'A', 'webmail', 3600, '$IP', UNIX_TIMESTAMP())" 2>/dev/null \
            && { CHANGED="$CHANGED $ID"; echo "DNS: registro A webmail anadido a $NAME"; }
    done

    if [ -n "$CHANGED" ]; then
        CUR=$($MY -e "SELECT so_value_tx FROM \`$DB\`.x_settings WHERE so_name_vc='dns_hasupdates'" 2>/dev/null)
        NEW=$(printf '%s\n%s\n' "$(echo "$CUR" | tr ',' '\n')" "$(echo "$CHANGED" | tr ' ' '\n')" \
              | sed '/^[[:space:]]*$/d' | sort -un | paste -sd ',' -)
        $MY -e "UPDATE \`$DB\`.x_settings SET so_value_tx='$NEW' WHERE so_name_vc='dns_hasupdates'" 2>/dev/null
        echo "dns_hasupdates marcado para regenerar las zonas: $NEW"
    else
        echo "backfill DNS: todas las zonas tenian ya el registro A webmail"
    fi
fi

# 3) Regenerar httpd-vhosts.conf (vhosts de redireccion webmail.*)
$MY -e "UPDATE \`$DB\`.x_settings SET so_value_tx='true' WHERE so_name_vc='apache_changed'" 2>/dev/null
echo "apache_changed=true: el daemon regenerara httpd-vhosts.conf con la redireccion webmail"
exit 0

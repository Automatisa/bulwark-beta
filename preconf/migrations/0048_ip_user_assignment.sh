#!/bin/sh
# 0048_ip_user_assignment.sh — Multi-IP Fase 2b: IPs ASIGNADAS a usuarios.
# (a) x_ips.ip_user_fk marca la IP asignada a una cuenta concreta (NULL = libre en el
#     pool). La cuota de IPs dedicadas del paquete limita cuántas IPs se pueden asignar
#     a cada usuario; el usuario las usa a su discreción entre sus dominios.
# (b) Backfill: las IPs ya en uso por dominios quedan asignadas a su propietario.
# (c) Concede al grupo 2 (resellers) acceso al módulo autoip para que distribuyan las
#     IPs de su pool entre sus clientes; las secciones de admin quedan ocultas en la
#     plantilla con <% if Admin %> (mismo patrón que 0016 con imapsync).
# Idempotente.

DBPHP=/usr/local/bulwark/cnf/db.php
U=$(php -r "include \"$DBPHP\"; echo \$user;")
P=$(php -r "include \"$DBPHP\"; echo \$pass;")
H=$(php -r "include \"$DBPHP\"; echo \$host;")
DB=$(php -r "include \"$DBPHP\"; echo \$dbname;")
MY="mysql -u$U -p$P -h$H -N"

# (a) columna ip_user_fk
HAS=$($MY -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='$DB' AND table_name='x_ips' AND column_name='ip_user_fk'" 2>/dev/null)
if [ "$HAS" = "0" ]; then
    $MY -e "ALTER TABLE \`$DB\`.x_ips ADD COLUMN ip_user_fk INT(10) DEFAULT NULL COMMENT 'FK a x_accounts: usuario al que está asignada la IP; NULL = libre en el pool', ADD KEY idx_ip_user (ip_user_fk)" 2>/dev/null
    echo "x_ips.ip_user_fk creada"
else
    echo "x_ips.ip_user_fk ya existe"
fi

# (b) backfill: IP en uso por dominios -> asignada a su propietario
$MY -e "UPDATE \`$DB\`.x_ips ip JOIN (SELECT vh_custom_ip_vc ip, MIN(vh_acc_fk) acc FROM \`$DB\`.x_vhosts WHERE vh_custom_ip_vc IS NOT NULL AND vh_custom_ip_vc<>'' AND vh_deleted_ts IS NULL GROUP BY vh_custom_ip_vc) u ON ip.ip_address_vc=u.ip SET ip.ip_user_fk=u.acc WHERE ip.ip_user_fk IS NULL" 2>/dev/null
echo "backfill de IPs en uso completado"

# (c) permiso de resellers (grupo 2) al módulo autoip
MOID=$($MY -e "SELECT mo_id_pk FROM \`$DB\`.x_modules WHERE mo_folder_vc='autoip' LIMIT 1" 2>/dev/null)
if [ -n "$MOID" ]; then
    PHAS=$($MY -e "SELECT COUNT(*) FROM \`$DB\`.x_permissions WHERE pe_group_fk=2 AND pe_module_fk=$MOID" 2>/dev/null)
    if [ "$PHAS" = "0" ]; then
        $MY -e "INSERT INTO \`$DB\`.x_permissions (pe_group_fk,pe_module_fk) VALUES (2,$MOID)" 2>/dev/null
        echo "permiso de autoip concedido al grupo 2 (resellers)"
    else
        echo "el grupo 2 ya tenia permiso de autoip"
    fi
else
    echo "AVISO: modulo autoip no encontrado en x_modules"
fi
exit 0

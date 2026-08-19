#!/bin/sh
# 0041_clam_addheader_force_actions.sh — en modo "add header" el correo con virus
# se entrega marcado SIEMPRE (passthrough absoluto), nunca se rechaza en SMTP.
#
# La opcion "action" del modulo antivirus de rspamd es un MINIMO ("least"): un
# correo infectado que ademas acumula score por adjuntos sospechosos
# (MIME_BAD_EXTENSION 8.0, SINGLE_FILE_ARCHIVE_WITH_EXE 5.0, EXE_IN_ARCHIVE 1.5...)
# supera el umbral de reject (15) y rspamd lo rechazaba en SMTP aunque el admin
# hubiera elegido "add header" en clamav_admin (p.ej. EICAR adjuntado como .com:
# 23.70/15.00 → reject). Se regenera /var/bulwark/clamav/antivirus.conf con una
# regla force_actions SIN "least" (passthrough absoluto a "add header" cuando
# existe CLAM_VIRUS) y se recarga rspamd. Idempotente: reescribe el fichero
# completo desde el estado vigente en redis (bulwark:clamav).

CONF=/var/bulwark/clamav/antivirus.conf
[ -d /var/bulwark/clamav ] || { echo "no existe /var/bulwark/clamav; nada que hacer"; exit 0; }

# Estado vigente (redis) — mismo origen de verdad que el panel.
enabled=0
action=reject
RP=''
[ -r /usr/local/bulwark/cnf/redis.pass ] && RP=$(tr -d '[:space:]' < /usr/local/bulwark/cnf/redis.pass)
if [ -n "$RP" ] && command -v redis-cli > /dev/null 2>&1; then
    e=$(redis-cli --user panel --pass "$RP" --no-auth-warning HGET bulwark:clamav email_enabled 2>/dev/null)
    a=$(redis-cli --user panel --pass "$RP" --no-auth-warning HGET bulwark:clamav email_action 2>/dev/null)
    [ "$e" = "1" ] && enabled=1
    [ "$a" = "add header" ] && action="add header"
fi

if [ "$enabled" != "1" ]; then
    printf '# ClamAV email scanning desactivado\n' > "$CONF"
    echo "proteccion de email desactivada; $CONF regenerado sin reglas"
else
    {
        echo "# Generado por Bulwark clamav_admin — no editar manualmente"
        echo "clamav {"
        echo "    action        = \"$action\";"
        echo "    scan_mime_parts = true;"
        echo "    scan_text_mime  = false;"
        echo "    scan_image_mime = false;"
        echo "    symbol          = \"CLAM_VIRUS\";"
        echo "    type            = \"clamav\";"
        echo "    servers         = \"127.0.0.1:3310\";"
        echo "    timeout         = 15.0;"
        echo "    retransmits     = 2;"
        echo "    log_clean       = false;"
        echo "}"
        if [ "$action" != "reject" ]; then
            echo "force_actions {"
            echo "    rules {"
            echo "        clam_virus_deliver_marked {"
            echo "            expression = \"CLAM_VIRUS\";"
            echo "            action     = \"add header\";"
            echo "            message    = \"clamav: virus found - entregado marcado como spam (politica del panel)\";"
            echo "        }"
            echo "    }"
            echo "}"
        fi
    } > "$CONF"
    echo "$CONF regenerado (enabled=$enabled action=$action)"
fi

# Conservar propietario/grupo actuales (el panel escribe como usuario propio).
own=$(stat -f '%Su:%Sg' "$CONF" 2>/dev/null)
[ -n "$own" ] || own="bulwark:www"
chown "$own" "$CONF" 2>/dev/null || true
chmod 640 "$CONF"

# Recargar rspamd si esta en ejecucion (pidfile; pgrep -x falla en FreeBSD).
if [ -s /var/run/rspamd/rspamd.pid ] && kill -0 "$(cat /var/run/rspamd/rspamd.pid)" 2>/dev/null; then
    if /usr/sbin/service rspamd reload > /dev/null 2>&1; then
        echo "rspamd recargado"
    else
        echo "aviso: no se pudo recargar rspamd; se aplicara en el proximo arranque"
    fi
else
    echo "rspamd no esta en ejecucion; la config se aplicara al arrancar"
fi
exit 0

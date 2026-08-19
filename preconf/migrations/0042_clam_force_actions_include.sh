#!/bin/sh
# 0042_clam_force_actions_include.sh — la regla force_actions generada por la
# mig. 0041 / clamav_admin quedaba DENTRO del include de local.d/antivirus.conf,
# y rspamd funde ese include en la seccion "antivirus" → "unknown antivirus
# type: force_actions" (syntax BAD en configtest; el reload conserva la config
# anterior). force_actions es una seccion de PRIMER nivel: necesita su propio
# fichero en local.d con su propio include dinamico. Esta migracion:
#   1) crea /usr/local/etc/rspamd/local.d/force_actions.conf (include dinamico),
#   2) regenera separados /var/bulwark/clamav/antivirus.conf (solo clamav{}) y
#      /var/bulwark/clamav/force_actions.conf (rules{} solo en modo add header),
#   3) recarga rspamd. Idempotente (reescribe todo desde el estado en redis).

AV=/var/bulwark/clamav/antivirus.conf
FA=/var/bulwark/clamav/force_actions.conf
[ -d /var/bulwark/clamav ] || { echo "no existe /var/bulwark/clamav; nada que hacer"; exit 0; }

# 1) include estatico de primer nivel (root).
cat > /usr/local/etc/rspamd/local.d/force_actions.conf << 'RSFA'
.include(try=true,priority=10) "/var/bulwark/clamav/force_actions.conf"
RSFA
echo "local.d/force_actions.conf creado"

# 2) regenerar dinamicos desde el estado vigente (redis), mismo criterio que el panel.
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
    printf '# ClamAV email scanning desactivado\n' > "$AV"
    printf '# Generado por Bulwark clamav_admin — no editar manualmente\n' > "$FA"
    echo "proteccion de email desactivada; dinamicos regenerados sin reglas"
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
    } > "$AV"
    {
        echo "# Generado por Bulwark clamav_admin — no editar manualmente"
        if [ "$action" != "reject" ]; then
            # force_actions SIN "least" = passthrough absoluto: con CLAM_VIRUS la
            # accion final es "add header" aunque el score supere el umbral de
            # reject por adjuntos sospechosos (MIME_BAD_EXTENSION, EXE_IN_ARCHIVE...).
            echo "rules {"
            echo "    clam_virus_deliver_marked {"
            echo "        expression = \"CLAM_VIRUS\";"
            echo "        action     = \"add header\";"
            echo "        message    = \"clamav: virus found - entregado marcado como spam (politica del panel)\";"
            echo "    }"
            echo "}"
        fi
    } > "$FA"
    echo "dinamicos regenerados (enabled=$enabled action=$action)"
fi

# Conservar propietario/grupo actuales (el panel escribe como usuario propio).
for f in "$AV" "$FA"; do
    own=$(stat -f '%Su:%Sg' "$f" 2>/dev/null)
    [ -n "$own" ] || own="bulwark:www"
    chown "$own" "$f" 2>/dev/null || true
    chmod 640 "$f"
done

# 3) recargar rspamd si esta en ejecucion (pidfile; pgrep -x falla en FreeBSD).
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

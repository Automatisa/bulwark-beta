#!/bin/sh
# 0044_clam_virus_subject_include.sh — la mig. 0043 escribia el patron de asunto
# (rewrite_subject.subject) DENTRO de /var/bulwark/clamav/force_actions.conf, pero
# local.d/force_actions.conf funde su include en la seccion "force_actions": el
# patron quedaba anidado donde rspamd no lo lee (configdump actions no lo veia) y
# el asunto se reescribia con el patron por defecto en vez del aviso de virus.
# El patron debe vivir en la seccion de primer nivel "actions", que necesita su
# propio include estatico. Esta migracion:
#   1) crea /usr/local/etc/rspamd/local.d/actions.conf (include dinamico) y el
#      dinamico /var/bulwark/clamav/virus_subject.conf (propietario del panel),
#   2) regenera force_actions.conf SIN el bloque actions{} anidado y
#      virus_subject.conf con el patron (solo en modo no-reject),
#   3) recarga rspamd. Idempotente (reescribe todo desde el estado en redis).

FA=/var/bulwark/clamav/force_actions.conf
VS=/var/bulwark/clamav/virus_subject.conf
[ -d /var/bulwark/clamav ] || { echo "no existe /var/bulwark/clamav; nada que hacer"; exit 0; }

# 1) include estatico de primer nivel para la seccion "actions" (root).
cat > /usr/local/etc/rspamd/local.d/actions.conf << 'RSAC'
.include(try=true,priority=10) "/var/bulwark/clamav/virus_subject.conf"
RSAC
[ -f "$VS" ] || touch "$VS"
own=$(stat -f '%Su:%Sg' "$VS" 2>/dev/null)
[ -n "$own" ] || own="bulwark:www"
chown "$own" "$VS" 2>/dev/null || true
chmod 640 "$VS"
echo "local.d/actions.conf creado; virus_subject.conf listo"

# 2) regenerar dinamicos con el mismo criterio que el panel (redis bulwark:clamav).
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

{
    echo "# Generado por Bulwark clamav_admin — no editar manualmente"
    if [ "$enabled" = "1" ] && [ "$action" != "reject" ]; then
        # force_actions SIN "least" = passthrough absoluto: con CLAM_VIRUS la accion
        # final es "rewrite subject" (>= "add header"): conserva X-Spam: Yes (sieve ->
        # carpeta Spam) y reescribe el asunto con el aviso de virus.
        echo "rules {"
        echo "    clam_virus_deliver_marked {"
        echo "        expression = \"CLAM_VIRUS\";"
        echo "        action     = \"rewrite subject\";"
        echo "        message    = \"clamav: virus found - entregado marcado con aviso de virus (politica del panel)\";"
        echo "    }"
        echo "}"
    fi
} > "$FA"

{
    echo "# Generado por Bulwark clamav_admin — no editar manualmente"
    if [ "$enabled" = "1" ] && [ "$action" != "reject" ]; then
        echo "rewrite_subject {"
        echo "    subject = \"***VIRUS*** %s\";"
        echo "}"
    fi
} > "$VS"

for f in "$FA" "$VS"; do
    own=$(stat -f '%Su:%Sg' "$f" 2>/dev/null)
    [ -n "$own" ] || own="bulwark:www"
    chown "$own" "$f" 2>/dev/null || true
    chmod 640 "$f"
done
echo "dinamicos regenerados (enabled=$enabled action=$action)"

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

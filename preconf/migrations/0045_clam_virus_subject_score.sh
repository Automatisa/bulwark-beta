#!/bin/sh
# 0045_clam_virus_subject_score.sh — la mig. 0044 escribia rewrite_subject.subject
# SIN score en /var/bulwark/clamav/virus_subject.conf, y rspamd exige umbral en cada
# accion de "actions" ("action rewrite subject has no threshold being set" → syntax
# BAD, reload rechazado). Esta migracion regenera virus_subject.conf con
# score = 9999 (inalcanzable por puntuacion; la accion solo se aplica via
# force_actions con CLAM_VIRUS) y recarga rspamd. Idempotente.

VS=/var/bulwark/clamav/virus_subject.conf
[ -d /var/bulwark/clamav ] || { echo "no existe /var/bulwark/clamav; nada que hacer"; exit 0; }

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
        echo "rewrite_subject {"
        echo "    score = 9999;"
        echo "    subject = \"***VIRUS*** %s\";"
        echo "}"
    fi
} > "$VS"

own=$(stat -f '%Su:%Sg' "$VS" 2>/dev/null)
[ -n "$own" ] || own="bulwark:www"
chown "$own" "$VS" 2>/dev/null || true
chmod 640 "$VS"
echo "virus_subject.conf regenerado (enabled=$enabled action=$action)"

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

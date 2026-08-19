#!/bin/sh
# 0043_clam_virus_subject_warning.sh — en modo "add header" el correo con virus se
# entregaba en la carpeta Spam pero SIN aviso visible (asunto intacto; solo la
# cabecera X-Spam). Ahora la regla force_actions fuerza la accion "rewrite subject"
# y el asunto se reescribe a "***VIRUS*** <asunto original>" (aviso de virus visible
# en cualquier cliente de correo). Esta migracion:
#   1) regenera /var/bulwark/clamav/force_actions.conf con el nuevo formato
#      (rules{} + actions.rewrite_subject.subject) desde el estado en redis,
#   2) anade el asunto "***VIRUS***" al filtro sieve global (cinturon de seguridad
#      ademas de la cabecera X-Spam: Yes),
#   3) recarga rspamd. Idempotente (reescribe todo desde el estado en redis).

FA=/var/bulwark/clamav/force_actions.conf
[ -d /var/bulwark/clamav ] || { echo "no existe /var/bulwark/clamav; nada que hacer"; exit 0; }

# 1) regenerar el dinamico con el mismo criterio que el panel (redis bulwark:clamav).
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
        # final es "rewrite subject" (>= "add header"): conserva X-Spam: Yes (el sieve
        # global lo deposita en Spam) y reescribe el asunto con el aviso de virus.
        echo "rules {"
        echo "    clam_virus_deliver_marked {"
        echo "        expression = \"CLAM_VIRUS\";"
        echo "        action     = \"rewrite subject\";"
        echo "        message    = \"clamav: virus found - entregado marcado con aviso de virus (politica del panel)\";"
        echo "    }"
        echo "}"
        echo "actions {"
        echo "    rewrite_subject {"
        echo "        subject = \"***VIRUS*** %s\";"
        echo "    }"
        echo "}"
    fi
} > "$FA"

# Conservar propietario/grupo actuales (el panel escribe como usuario propio).
own=$(stat -f '%Su:%Sg' "$FA" 2>/dev/null)
[ -n "$own" ] || own="bulwark:www"
chown "$own" "$FA" 2>/dev/null || true
chmod 640 "$FA"
echo "force_actions.conf regenerado (enabled=$enabled action=$action)"

# 2) sieve global: aceptar tambien el asunto ***VIRUS*** (ademas de X-Spam: Yes).
SIEVE=/var/bulwark/sieve/globalfilter.sieve
if [ -f "$SIEVE" ]; then
    if grep -q '\*\*\*VIRUS\*\*\*' "$SIEVE"; then
        echo "filtro sieve global ya contempla ***VIRUS***; se respeta"
    else
        sed -i '' 's/\["\*\*\*SPAM\*\*\*"\]/["***SPAM***", "***VIRUS***"]/' "$SIEVE"
        rm -f /var/bulwark/sieve/globalfilter.svbin
        echo "filtro sieve global actualizado (asunto ***VIRUS*** -> carpeta Spam)"
    fi
else
    echo "no existe $SIEVE; se omite el filtro sieve"
fi

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

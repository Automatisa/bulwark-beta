#!/bin/sh
# 0046_clam_virus_warning_milter_headers.sh — el aviso de virus visible del modo
# "add header" no puede hacerse con la accion "rewrite subject": rspamd exige
# score por accion y el patron de asunto por defecto "*** SPAM ***" no sirve como
# aviso (y esa accion no anade X-Spam: Yes, asi que el sieve no llevaba el correo
# a Spam). Nuevo mecanismo: el modulo milter_headers, con la rutina integrada
# x-virus (cabecera X-Virus con el nombre del virus) y una rutina custom Lua que
# reescribe el asunto a "***VIRUS*** <asunto>" cuando existe CLAM_VIRUS. Esta
# migracion:
#   1) escribe /usr/local/etc/rspamd/local.d/milter_headers.conf (estatico),
#   2) regenera force_actions.conf con passthrough a "add header" (X-Spam: Yes ->
#      sieve lleva el correo a Spam) y virus_subject.conf sin reglas,
#   3) recarga rspamd. Idempotente.

FA=/var/bulwark/clamav/force_actions.conf
VS=/var/bulwark/clamav/virus_subject.conf
[ -d /var/bulwark/clamav ] || { echo "no existe /var/bulwark/clamav; nada que hacer"; exit 0; }

# 1) milter_headers: aviso visible de virus (asunto ***VIRUS*** + cabecera X-Virus).
cat > /usr/local/etc/rspamd/local.d/milter_headers.conf << 'RSMH'
use = ["x-virus", "clam_virus_subject"];
skip_local = false;
skip_authenticated = false;
routines {
    x-virus {
        symbols = ["CLAM_VIRUS"];
        status_infected = "yes";
    }
}
custom {
    clam_virus_subject = <<EOD
return function(task, common_meta)
  if task:has_symbol('CLAM_VIRUS') then
    local subj = task:get_header('Subject') or ''
    if not subj:match('^%*%*%*VIRUS%*%*%*') then
      return nil, { ['Subject'] = '***VIRUS*** ' .. subj }, { ['Subject'] = 0 }, {}
    end
  end
  return nil, {}, {}, {}
end
EOD
}
RSMH
echo "local.d/milter_headers.conf escrito"

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
        # final es "add header" (X-Spam: Yes -> sieve a Spam) aunque el score supere
        # el umbral de reject por adjuntos sospechosos.
        echo "rules {"
        echo "    clam_virus_deliver_marked {"
        echo "        expression = \"CLAM_VIRUS\";"
        echo "        action     = \"add header\";"
        echo "        message    = \"clamav: virus found - entregado marcado con aviso de virus (politica del panel)\";"
        echo "    }"
        echo "}"
    fi
} > "$FA"

{
    echo "# Generado por Bulwark clamav_admin — no editar manualmente"
    echo "# El aviso de virus visible lo aplica milter_headers (local.d/milter_headers.conf)."
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

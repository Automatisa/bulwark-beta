#!/bin/sh
# 0040_clam_virus_symbol_weight.sh — peso para el simbolo CLAM_VIRUS de rspamd.
#
# El modulo antivirus de rspamd registra el simbolo CLAM_VIRUS con score 0 si no hay
# peso definido en groups.conf. Consecuencias: en el historial la deteccion aparece
# con score 0.00 (no parece "marcada como virus") y, sobre todo, con la accion
# "add header" el correo infectado se entregaba SIN ninguna marca de spam (score 0
# no supera el umbral de 6), contradiciendo lo que promete la UI de clamav_admin.
# Se añade el grupo "viruses" con weight 10.0 (> umbral "add header" = 6, < "reject"
# = 15): en modo "add header" el correo se entrega marcado como spam y el filtro
# sieve global lo manda a la carpeta Spam; en modo "reject" rspamd ya rechaza por
# passthrough, asi que el peso es solo informativo. Idempotente.

GC=/usr/local/etc/rspamd/local.d/groups.conf
[ -f "$GC" ] || { echo "no existe $GC; nada que hacer"; exit 0; }

if grep -q "CLAM_VIRUS" "$GC"; then
    echo "CLAM_VIRUS ya tiene peso definido en groups.conf; se respeta"
else
    cat >> "$GC" <<'EOF'

group "viruses" {
    symbols {
        "CLAM_VIRUS"        { weight = 10.0; description = "Virus detectado por ClamAV"; one_shot = true; }
    }
}
EOF
    echo "añadido grupo viruses (CLAM_VIRUS weight=10.0) a groups.conf"
fi

# Recargar rspamd si está en ejecución para aplicar el cambio ya
if pgrep -x rspamd > /dev/null 2>&1; then
    if /usr/sbin/service rspamd reload > /dev/null 2>&1; then
        echo "rspamd recargado"
    else
        echo "aviso: no se pudo recargar rspamd; se aplicará en el próximo arranque"
    fi
else
    echo "rspamd no está en ejecución; la config se aplicará al arrancar"
fi

# Filtro sieve global: que el correo marcado por rspamd (cabecera X-Spam) vaya a
# la carpeta Spam. El filtro antiguo solo miraba X-Spam-Flag (SpamAssassin) y el
# asunto ***SPAM***, y rspamd en modo milter anade "X-Spam: Yes". Sin esto, el
# correo con virus en modo "add header" se entregaria en INBOX sin clasificar.
SIEVE=/var/bulwark/sieve/globalfilter.sieve
if [ -f "$SIEVE" ]; then
    if grep -q 'header :contains "X-Spam" "Yes"' "$SIEVE"; then
        echo "filtro sieve global ya contempla la cabecera X-Spam; se respeta"
    else
        cat > "$SIEVE" <<'EOF'
require "fileinto";

# Cabecera X-Spam: rspamd (modo milter) solo la anade cuando el mensaje alcanza
# la accion "add header" o superior — spam, o VIRUS con la accion "add header".
if header :contains "X-Spam" "Yes" {
        fileinto "Spam";
        stop;
}

# X-Spam-Flag: formato SpamAssassin (cualquier valor distinto de NO es spam).
if exists "X-Spam-Flag" {
        if header :contains "X-Spam-Flag" "NO" {
        } else {
        fileinto "Spam";
        stop;
        }
}

# Asunto reescrito por rspamd (accion "rewrite subject").
if header :contains "subject" ["***SPAM***"] {
  fileinto "Spam";
  stop;
}
EOF
        chown vmail:vmail "$SIEVE"
        rm -f /var/bulwark/sieve/globalfilter.svbin
        echo "filtro sieve global actualizado (X-Spam -> carpeta Spam)"
    fi
else
    echo "no existe $SIEVE; se omite el filtro sieve"
fi
exit 0
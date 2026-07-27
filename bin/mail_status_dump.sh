#!/bin/sh
# mail_status_dump.sh — Vuelca el estado del servidor de correo a JSON legible por el panel.
# Invocado ÚNICAMENTE por privilege::run('mail_status_dump'). Sin argumentos.
#
# Salida: /var/bulwark/logs/mail_status.json (propietario www:www, modo 640)
# Contiene: cola de Postfix, últimas líneas del maillog y contadores. SOLO LECTURA.

OUTPUT="/var/bulwark/logs/mail_status.json"
TMPFILE="/tmp/bulwark_mail_status.$$"
LOG="/var/log/maillog"
LINES=250

mkdir -p /var/bulwark/logs

# ---- Cola de correo (Postfix 3.1+: postqueue -j = un objeto JSON por mensaje) ----
queue_to_json() {
    postqueue -j 2>/dev/null | awk 'BEGIN{f=1}{ if($0=="")next; if(!f)printf ","; printf "%s",$0; f=0 }'
}
QUEUE_JSON=$(queue_to_json)
QUEUE_N=$(postqueue -j 2>/dev/null | grep -c '"queue_id"')
QUEUE_N=${QUEUE_N:-0}

# ---- Últimas líneas del maillog (escapadas para JSON) ----
log_to_json() {
    tail -n "$LINES" "$LOG" 2>/dev/null | awk 'BEGIN{f=1}{
        gsub(/\\/,"\\\\"); gsub(/"/,"\\\""); gsub(/\t/," ");
        gsub(/\r/,"");
        if(!f)printf ","; printf "\"%s\"",$0; f=0
    }'
}
LOG_JSON=$(log_to_json)

# ---- Contadores del maillog actual ----
cnt() { grep -c "$1" "$LOG" 2>/dev/null | tr -d ' \t'; }
SENT=$(cnt "status=sent");      SENT=${SENT:-0}
DEFER=$(cnt "status=deferred"); DEFER=${DEFER:-0}
BOUNCE=$(cnt "status=bounced"); BOUNCE=${BOUNCE:-0}
REJECT=$(grep -cE "NOQUEUE: reject|reject:" "$LOG" 2>/dev/null | tr -d ' \t'); REJECT=${REJECT:-0}
SASLFAIL=$(grep -c "SASL LOGIN authentication failed" "$LOG" 2>/dev/null | tr -d ' \t'); SASLFAIL=${SASLFAIL:-0}

TS=$(date +%s)

{
  printf '{'
  printf '"ts":%s,' "$TS"
  printf '"queue_count":%s,' "$QUEUE_N"
  printf '"stats":{"sent":%s,"deferred":%s,"bounced":%s,"rejected":%s,"sasl_failed":%s},' \
         "$SENT" "$DEFER" "$BOUNCE" "$REJECT" "$SASLFAIL"
  printf '"queue":[%s],' "$QUEUE_JSON"
  printf '"log":[%s]' "$LOG_JSON"
  printf '}'
} > "$TMPFILE" 2>/dev/null

if [ -s "$TMPFILE" ]; then
    mv "$TMPFILE" "$OUTPUT"
    chown www:www "$OUTPUT" 2>/dev/null
    chmod 640 "$OUTPUT" 2>/dev/null
else
    rm -f "$TMPFILE"
    exit 1
fi

#!/bin/sh
# 0034_rspamd_panel_owner.sh — Los ficheros dinámicos de antispam (/var/bulwark/rspamd/)
# los escribe el panel, que desde la migración a usuario propio corre como PANEL_USER
# (bulwark, miembro de www). El instalador antiguo los sembró www:www 640, así que el
# panel ya no puede escribirlos → "Error al escribir mapa de redirectores" al guardar
# la pestaña Phishing (toggle OpenPhish incluido). rspamd lee estos ficheros con su
# proceso main (root), por lo que basta recolocar el propietario al usuario del panel.
# Idempotente.

# Usuario real del panel: se lee del pool FPM (igual que panel_update.sh).
PANEL_USER=$(awk -F= '/^[[:space:]]*user[[:space:]]*=/{gsub(/[[:space:]]/,"",$2);print $2;exit}' \
             /usr/local/etc/php-fpm.d/www.conf 2>/dev/null)
[ -n "$PANEL_USER" ] || PANEL_USER=bulwark

# El usuario del panel debe pertenecer a www para escribir en el directorio 2770.
if ! id -Gn "$PANEL_USER" 2>/dev/null | tr ' ' '\n' | grep -qx www; then
    pw groupmod www -m "$PANEL_USER" && echo "$PANEL_USER añadido al grupo www"
fi

D=/var/bulwark/rspamd
if [ ! -d "$D" ]; then
    echo "$D no existe (instalación sin rspamd); nada que hacer"
    exit 0
fi

chown www:www "$D"
chmod 2770 "$D"

for f in options.inc ratelimit.conf rbl.conf phishing.conf \
         phishing_redirectors.map phishing_strict_domains.map; do
    [ -f "$D/$f" ] || continue
    chown "$PANEL_USER":www "$D/$f"
    chmod 640 "$D/$f"
    echo "reparado $D/$f -> $PANEL_USER:www 640"
done

exit 0

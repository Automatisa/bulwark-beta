#!/bin/sh
# 0036_clamav_quarantine_admin.sh — la UI de clamav_admin (panel) lista, descarga y borra los
# archivos de la cuarentena directamente (scandir/readfile/unlink como usuario del panel),
# pero 0035 la dejó en root:wheel 700 → la pestaña Cuarentena del admin no veía nada y el
# borrado/descarga fallaban. Estado canónico: root:<panel> 770 (root escribe vía post-scan y
# restore; el panel accede por grupo; www/Apache sin acceso al malware). También corrige la
# deriva de scan_results.log (los scripts de escaneo hacían chmod 644; matriz = 640).
# Idempotente; solo chown/chmod.

PANEL_USER=$(awk -F= '/^[[:space:]]*user[[:space:]]*=/{gsub(/[[:space:]]/,"",$2);print $2;exit}' \
             /usr/local/etc/php-fpm.d/www.conf 2>/dev/null)
[ -n "$PANEL_USER" ] || PANEL_USER=bulwark
id "$PANEL_USER" >/dev/null 2>&1 || { echo "usuario $PANEL_USER no existe; nada que hacer"; exit 0; }

Q=/var/bulwark/clamav/quarantine
if [ -d "$Q" ]; then
    chown root:"$PANEL_USER" "$Q"
    chmod 770 "$Q"
    echo "reparado $Q -> root:$PANEL_USER 770"
fi

L=/var/bulwark/clamav/scan_results.log
[ -f "$L" ] && chmod 640 "$L"

echo "0036: cuarentena ClamAV accesible para clamav_admin (usuario $PANEL_USER)"
exit 0
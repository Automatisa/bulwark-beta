#!/bin/sh
# 0047_clam_virus_subject_owner.sh — la mig. 0044 creo virus_subject.conf como
# root; el panel (usuario bulwark) debe poder escribirlo como el resto de
# dinamicos de /var/bulwark/clamav (640 panel:www). Idempotente.

VS=/var/bulwark/clamav/virus_subject.conf
[ -f "$VS" ] || { echo "no existe $VS; nada que hacer"; exit 0; }
PANEL_USER=$(stat -f '%Su' /var/bulwark/clamav/antivirus.conf 2>/dev/null)
[ -n "$PANEL_USER" ] || PANEL_USER=bulwark
chown "$PANEL_USER":www "$VS" && chmod 640 "$VS"
echo "virus_subject.conf -> $PANEL_USER:www 640"
exit 0

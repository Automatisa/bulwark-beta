#!/bin/sh
# 0035_runtime_writable.sh — Auditoría completa de permisos del runtime del panel (continuación
# de 0034). El panel ya no corre como 'www' sino como usuario propio PANEL_USER (miembro de
# www); el instalador antiguo sembró varios paths de runtime www:www o root:wheel SIN escritura
# para el usuario del panel, así que esas funciones fallan en caliente (mismo patrón que los
# mapas de phishing de 0034): backups completos, certificados Let's Encrypt, configs de ClamAV,
# límites de correo saliente, staging de crontab, logs del panel, etc/tmp y secretos cnf/ que
# un fix_permissions antiguo revirtió a root:www. Esta migración repara todo el conjunto.
# Idempotente; solo chown/chmod, nunca reescribe contenidos.

# Usuario real del panel: se lee del pool FPM (igual que 0034/panel_update.sh).
PANEL_USER=$(awk -F= '/^[[:space:]]*user[[:space:]]*=/{gsub(/[[:space:]]/,"",$2);print $2;exit}' \
             /usr/local/etc/php-fpm.d/www.conf 2>/dev/null)
[ -n "$PANEL_USER" ] || PANEL_USER=bulwark
id "$PANEL_USER" >/dev/null 2>&1 || { echo "usuario $PANEL_USER no existe; nada que hacer"; exit 0; }

D=/var/bulwark
P=/usr/local/bulwark

# 1. backups/: el panel (backupmgr/dobackup.php) escribe los backups completos.
[ -d "$D/backups" ] && { chown "$PANEL_USER":www "$D/backups"; chmod 2770 "$D/backups"; \
                         echo "reparado $D/backups -> $PANEL_USER:www 2770"; }

# 2. ssl/sencrypt/: cuentas ACME y certificados (Lescript escribe como panel).
[ -d "$D/ssl/sencrypt" ] && chown -R "$PANEL_USER":www "$D/ssl/sencrypt"
[ -d "$D/ssl" ] && { chown "$PANEL_USER":www "$D/ssl"; chmod 750 "$D/ssl"; \
                     echo "reparado $D/ssl -> $PANEL_USER:www 750"; }

# 3. clamav/: configs que escribe clamav_admin. quarantine y scan_results.log siguen root.
if [ -d "$D/clamav" ]; then
    chown "$PANEL_USER":www "$D/clamav"; chmod 2770 "$D/clamav"
    for f in antivirus.conf freshclam_checks.conf scan_paths.conf scan_schedule.conf; do
        [ -f "$D/clamav/$f" ] && { chown "$PANEL_USER":www "$D/clamav/$f"; chmod 640 "$D/clamav/$f"; \
                                   echo "reparado $D/clamav/$f -> $PANEL_USER:www 640"; }
    done
    [ -d "$D/clamav/quarantine" ] && { chown root:wheel "$D/clamav/quarantine"; chmod 700 "$D/clamav/quarantine"; }
    [ -f "$D/clamav/scan_results.log" ] && chown root:www "$D/clamav/scan_results.log"
fi

# 4. mail_limits/: limit/whitelist los escribe antispam_admin (redis_pass no se toca:
#    es secreto del grupo maillimit).
if [ -d "$D/mail_limits" ]; then
    chown "$PANEL_USER":www "$D/mail_limits"; chmod 2770 "$D/mail_limits"
    for f in limit whitelist; do
        [ -f "$D/mail_limits/$f" ] && { chown "$PANEL_USER":www "$D/mail_limits/$f"; chmod 640 "$D/mail_limits/$f"; \
                                        echo "reparado $D/mail_limits/$f -> $PANEL_USER:www 640"; }
    done
fi

# 5. cron/: staging de crontab (www.cron) que escribe el panel e instala cron_install.sh.
[ -d "$D/cron" ] && { chown "$PANEL_USER":www "$D/cron"; chmod 2770 "$D/cron"; \
                      echo "reparado $D/cron -> $PANEL_USER:www 2770"; }

# 6. run/: peticiones privilegiadas (panel crea, wrappers root consumen) + imapsync.
[ -d "$D/run" ] && { chown root:"$PANEL_USER" "$D/run"; chmod 2770 "$D/run"; }
[ -d "$D/run/imapsync" ] && { chown "$PANEL_USER":www "$D/run/imapsync"; chmod 2770 "$D/run/imapsync"; }

# 7. acme-challenge/: tokens HTTP-01 (panel escribe, Apache sirve vía Alias).
[ -d "$D/acme-challenge" ] && { chown "$PANEL_USER":www "$D/acme-challenge"; chmod 2775 "$D/acme-challenge"; }

# 8. Sesiones y logs que append-ea el proceso FPM.
[ -d "$D/sessions" ] && { chown "$PANEL_USER":www "$D/sessions"; chmod 1733 "$D/sessions"; }
for f in bulwark.log bulwark-access.log bulwark-error.log bulwark-bandwidth.log php_errors.log; do
    [ -f "$D/logs/$f" ] && { chown "$PANEL_USER":www "$D/logs/$f"; chmod 660 "$D/logs/$f"; }
done

# 9. etc/tmp del panel (storage de plantillas, temporales de backup/SSL).
[ -d "$P/etc/tmp" ] && { chown -R "$PANEL_USER":www "$P/etc/tmp"; chmod 2775 "$P/etc/tmp"; }

# 10. Secretos cnf/: root:PANEL_USER 640 (un fix_permissions antiguo los revirtió a
#     root:www y www NO debe leer credenciales; fuente única: bin/migrate_panel_user.sh).
for f in cnf/db.php cnf/security.php cnf/redis.pass cnf/backup.key; do
    [ -f "$P/$f" ] && { chown root:"$PANEL_USER" "$P/$f"; chmod 640 "$P/$f"; }
done

echo "0035: runtime escribible del panel reparado (usuario $PANEL_USER)"
exit 0

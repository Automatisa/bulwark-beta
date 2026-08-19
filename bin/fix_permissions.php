<?php
/**
 * fix_permissions.php
 * Verifica y corrige permisos, propietarios y estructura de directorios de Bulwark.
 * Uso:
 *   php /usr/local/bulwark/bin/fix_permissions.php          # verifica y corrige
 *   php /usr/local/bulwark/bin/fix_permissions.php --check  # solo informa, no cambia nada
 */

$DRY_RUN = in_array('--check', $argv ?? []);

$PANEL_PATH = '/usr/local/bulwark';
$PANEL_DATA = '/var/bulwark';
$PANEL_CONF = '/usr/local/etc/bulwark';

// Usuario real del panel: se lee del pool FPM (no se hardcodea), igual que panel_update.sh.
$PANEL_USER = trim((string)@shell_exec(
    "awk -F= '/^[[:space:]]*user[[:space:]]*=/{gsub(/[[:space:]]/,\"\",\$2);print \$2;exit}' "
    . "/usr/local/etc/php-fpm.d/www.conf 2>/dev/null"
));
if ($PANEL_USER === '') $PANEL_USER = 'bulwark';

// ─── helpers ─────────────────────────────────────────────────────────────────

$fixed  = 0;
$issues = 0;
$errors = [];

function uid_of(string $name): int {
    $r = posix_getpwnam($name);
    return $r ? (int)$r['uid'] : -1;
}
function gid_of(string $name): int {
    $r = posix_getgrnam($name);
    return $r ? (int)$r['gid'] : -1;
}
function octal(int $mode): string {
    return substr(sprintf('%04o', $mode & 07777), -4);
}

function check_path(string $path, int $want_mode, string $want_user, string $want_group,
                    bool $recursive = false): void
{
    global $DRY_RUN, $fixed, $issues, $errors;

    if (!file_exists($path)) {
        echo "  [FALTA]  $path\n";
        $issues++;
        return;
    }

    $stat  = stat($path);
    $cur_mode  = $stat['mode'] & 07777;
    $cur_uid   = $stat['uid'];
    $cur_gid   = $stat['gid'];
    $want_uid  = uid_of($want_user);
    $want_gid  = gid_of($want_group);
    $ok        = true;

    if ($cur_mode !== $want_mode) {
        echo "  [MODO]   $path  es:" . octal($cur_mode) . "  quiere:" . octal($want_mode) . "\n";
        if (!$DRY_RUN) { chmod($path, $want_mode); $fixed++; }
        $ok = false; $issues++;
    }
    if ($want_uid >= 0 && $cur_uid !== $want_uid) {
        echo "  [OWNER]  $path  es:" . posix_getpwuid($cur_uid)['name']
             . "  quiere:$want_user\n";
        if (!$DRY_RUN) { chown($path, $want_user); $fixed++; }
        $ok = false; $issues++;
    }
    if ($want_gid >= 0 && $cur_gid !== $want_gid) {
        echo "  [GROUP]  $path  es:" . posix_getgrgid($cur_gid)['name']
             . "  quiere:$want_group\n";
        if (!$DRY_RUN) { chgrp($path, $want_group); $fixed++; }
        $ok = false; $issues++;
    }

    if ($ok) echo "  [OK]     $path\n";

    if ($recursive && is_dir($path)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $item) {
            check_path((string)$item, $want_mode, $want_user, $want_group, false);
        }
    }
}

function check_glob(string $pattern, int $mode, string $user, string $group): void {
    $files = glob($pattern);
    if (empty($files)) {
        echo "  [VACIO]  $pattern  (sin coincidencias)\n";
        return;
    }
    foreach ($files as $f) check_path($f, $mode, $user, $group);
}

function section(string $title): void {
    echo "\n" . str_repeat('─', 60) . "\n";
    echo "  $title\n";
    echo str_repeat('─', 60) . "\n";
}

// ─── main ────────────────────────────────────────────────────────────────────

if (posix_geteuid() !== 0) {
    die("Este script debe ejecutarse como root.\n");
}

echo $DRY_RUN
    ? "\n=== fix_permissions.php [MODO REVISIÓN — sin cambios] ===\n"
    : "\n=== fix_permissions.php [MODO CORRECCIÓN] ===\n";

// ── 1. Raíz del mailstore ────────────────────────────────────────────────────
section("1. Mailstore /var/bulwark/vmail/");

check_path("$PANEL_DATA/vmail", 02770, 'vmail', 'vmail');

// Directorios de usuario: {vmail_root}/{paneluser}/ y {paneluser}/mail/
foreach (glob("$PANEL_DATA/vmail/*/") ?: [] as $userdir) {
    check_path(rtrim($userdir, '/'), 02770, 'vmail', 'vmail');
    $maildir = rtrim($userdir, '/') . '/mail';
    if (is_dir($maildir)) {
        check_path($maildir, 02770, 'vmail', 'vmail');
        // Directorios de dominio
        foreach (glob("$maildir/*/") ?: [] as $domdir) {
            check_path(rtrim($domdir, '/'), 02770, 'vmail', 'vmail');
            // Directorios de buzón
            foreach (glob(rtrim($domdir, '/') . "/*/") ?: [] as $mbdir) {
                $mb = rtrim($mbdir, '/');
                // El buzón en sí: 2770 para compatibilidad www:vmail
                check_path($mb, 02770, 'vmail', 'vmail');
                foreach (['cur','new','tmp',
                          '.Drafts','.Drafts/cur','.Drafts/new','.Drafts/tmp',
                          '.Sent','.Sent/cur','.Sent/new','.Sent/tmp',
                          '.Trash','.Trash/cur','.Trash/new','.Trash/tmp',
                          '.Junk','.Junk/cur','.Junk/new','.Junk/tmp'] as $sub) {
                    if (file_exists("$mb/$sub"))
                        check_path("$mb/$sub", 02770, 'vmail', 'vmail');
                }
                if (file_exists("$mb/subscriptions"))
                    check_path("$mb/subscriptions", 0640, 'vmail', 'vmail');
            }
        }
    }
}

// ── 2. Logs ──────────────────────────────────────────────────────────────────
section("2. Logs /var/bulwark/logs/");

check_path("$PANEL_DATA/logs",                0755, 'root',  'wheel');
check_path("$PANEL_DATA/logs/dovecot",        0750, 'vmail', 'vmail');
check_glob("$PANEL_DATA/logs/dovecot/*.log",  0640, 'vmail', 'vmail');
check_path("$PANEL_DATA/logs/bind",           0755, 'bind',  'bind');
check_glob("$PANEL_DATA/logs/bind/*.log",     0640, 'bind',  'bind');
check_path("$PANEL_DATA/logs/roundcube",      0755, 'www',   'www');
check_glob("$PANEL_DATA/logs/roundcube/*",    0640, 'www',   'www');
// Logs que append-ea el proceso FPM del panel → propietario el usuario del panel y
// escritura para que pueda escribirlos (antes www:www 640 → el panel no podía).
foreach (['bulwark.log','bulwark-access.log','bulwark-error.log',
          'bulwark-bandwidth.log','php_errors.log'] as $f) {
    if (file_exists("$PANEL_DATA/logs/$f"))
        check_path("$PANEL_DATA/logs/$f", 0660, $PANEL_USER, 'www');
}
// daemon-last-run.log lo escribe el daemon (root); el panel solo lo lee.
if (file_exists("$PANEL_DATA/logs/daemon-last-run.log"))
    check_path("$PANEL_DATA/logs/daemon-last-run.log", 0640, 'root', 'www');

// ── 3. Datos variables ───────────────────────────────────────────────────────
section("3. Datos variables /var/bulwark/");

check_path("$PANEL_DATA/sessions", 01733, $PANEL_USER, 'www');
check_path("$PANEL_DATA/temp",     01777, 'root', 'wheel');
check_path("$PANEL_DATA/hostdata", 0755,  'www',  'www');
// backups/: el panel (backupmgr/dobackup.php) escribe aquí; los scripts root de
// vhost-backup también. Dueño el usuario del panel para que la copia completa funcione.
check_path("$PANEL_DATA/backups",  02770, $PANEL_USER, 'www');
check_path("$PANEL_DATA/sieve",    0750,  'vmail','mail');
check_path("$PANEL_DATA/named",    0755,  'bind', 'bind');
check_path("$PANEL_DATA/named/data", 0755, 'bind','bind');

// ── 4. Panel ─────────────────────────────────────────────────────────────────
section("4. Panel $PANEL_PATH/");

check_path("$PANEL_PATH",          0755, 'root', 'wheel');
// Secretos: root:<usuario del panel> 640 — NUNCA root:www (Apache/contexto www no debe
// leer credenciales). Fuente única del aislamiento: bin/migrate_panel_user.sh.
foreach (['db.php', 'security.php', 'redis.pass', 'backup.key'] as $sec) {
    if (file_exists("$PANEL_PATH/cnf/$sec"))
        check_path("$PANEL_PATH/cnf/$sec", 0640, 'root', $PANEL_USER);
}
// etc/tmp lo escribe el panel (storage de plantillas, temporales de backup/SSL).
check_path("$PANEL_PATH/etc/tmp", 02775, $PANEL_USER, 'www');
check_path("$PANEL_PATH/bin",      0755, 'root', 'wheel');
check_glob("$PANEL_PATH/bin/*.php",  0750, 'root', 'wheel');
check_glob("$PANEL_PATH/bin/set*",   0750, 'root', 'wheel');
check_glob("$PANEL_PATH/bin/update*",0750, 'root', 'wheel');

// ── 5. Configuración sensible ────────────────────────────────────────────────
section("5. Configuración $PANEL_CONF/");

// Dovecot
check_path("$PANEL_CONF/dovecot2/dovecot.conf",          0644, 'root', 'wheel');
check_path("$PANEL_CONF/dovecot2/dovecot-mysql.conf",    0640, 'root', 'dovecot');
check_path("$PANEL_CONF/dovecot2/dovecot-dict-quota.conf",0640,'root', 'dovecot');
check_path("$PANEL_CONF/dovecot2/dovecot-trash.conf",    0644, 'root', 'wheel');

// Postfix MySQL maps
check_glob("$PANEL_CONF/postfix/mysql-*.cf", 0640, 'root', 'postfix');

// BIND
check_path("$PANEL_CONF/bind",               0755,  'bind', 'wheel');
check_path("$PANEL_CONF/bind/named.conf",    0640,  'bind', 'bind');
check_path("$PANEL_CONF/bind/rndc.key",      0640,  'bind', 'bind');
check_path("$PANEL_CONF/bind/rndc.conf",     0640,  'bind', 'bind');
check_path("$PANEL_CONF/bind/etc",           0775,  'bind', 'www');
check_path("$PANEL_CONF/bind/etc/named.conf",0664,  'bind', 'www');
check_path("$PANEL_CONF/bind/zones",         0775,  'bind', 'www');
check_glob("$PANEL_CONF/bind/zones/*.txt",   0664,  'bind', 'www');

// Apache
check_path("$PANEL_CONF/apache/httpd.conf",        0644, 'root', 'wheel');
check_path("$PANEL_CONF/apache/httpd-vhosts.conf", 0644, 'www',  'www');

// ── 6. Symlinks ──────────────────────────────────────────────────────────────
section("6. Symlinks");

$symlinks = [
    '/usr/local/etc/dovecot/dovecot.conf'    => "$PANEL_CONF/dovecot2/dovecot.conf",
    "$PANEL_PATH/etc/apps/webmail"           => '/usr/local/www/roundcube/public_html',
    "$PANEL_PATH/etc/apps/phpmyadmin"        => '/usr/local/www/phpMyAdmin',
];
foreach ($symlinks as $link => $target) {
    if (!is_link($link)) {
        echo "  [FALTA]  symlink $link -> $target\n";
        $issues++;
        if (!$DRY_RUN) { symlink($target, $link); $fixed++; }
    } elseif (readlink($link) !== $target) {
        echo "  [WRONG]  symlink $link apunta a " . readlink($link) . " (quiere $target)\n";
        $issues++;
        if (!$DRY_RUN) { unlink($link); symlink($target, $link); $fixed++; }
    } else {
        echo "  [OK]     $link -> $target\n";
    }
}

// ── 7. Antispam (ficheros dinámicos rspamd) ──────────────────────────────────
section("7. Antispam /var/bulwark/rspamd/");

// El directorio es www:www 2770: el usuario del panel (miembro de www) crea/borra
// ficheros y el setgid hereda el grupo www. Los ficheros los escribe el panel
// (<panel>:www 640) y rspamd los lee con su proceso main (root).
check_path("$PANEL_DATA/rspamd", 02770, 'www', 'www');
foreach (['options.inc', 'ratelimit.conf', 'rbl.conf', 'phishing.conf',
          'phishing_redirectors.map', 'phishing_strict_domains.map'] as $rsf) {
    if (file_exists("$PANEL_DATA/rspamd/$rsf"))
        check_path("$PANEL_DATA/rspamd/$rsf", 0640, $PANEL_USER, 'www');
}

// ── 8. Runtime escribible por el panel ───────────────────────────────────────
section("8. Runtime escribible por el panel ($PANEL_DATA)");

// run/: el panel crea ficheros de petición (fw, hosting, clamav, ftp, csr...); los
// wrappers root los consumen. root:<panel> 2770: 'www' no accede (pueden llevar
// credenciales, p.ej. imapsync/*.pass) y el setgid hereda el grupo del panel.
check_path("$PANEL_DATA/run", 02770, 'root', $PANEL_USER);
if (is_dir("$PANEL_DATA/run/imapsync"))
    check_path("$PANEL_DATA/run/imapsync", 02770, $PANEL_USER, 'www');

// cron/: staging de crontab que escribe el panel (www.cron) e instala cron_install.sh.
check_path("$PANEL_DATA/cron", 02770, $PANEL_USER, 'www');

// clamav/: configs las escribe clamav_admin (panel); quarantine y scan_results.log
// son territorio root (los scripts de escaneo corren privilegiados).
if (is_dir("$PANEL_DATA/clamav")) {
    check_path("$PANEL_DATA/clamav", 02770, $PANEL_USER, 'www');
    foreach (['antivirus.conf', 'freshclam_checks.conf', 'scan_paths.conf',
              'scan_schedule.conf'] as $cc) {
        if (file_exists("$PANEL_DATA/clamav/$cc"))
            check_path("$PANEL_DATA/clamav/$cc", 0640, $PANEL_USER, 'www');
    }
    if (file_exists("$PANEL_DATA/clamav/quarantine"))
        check_path("$PANEL_DATA/clamav/quarantine", 0700, 'root', 'wheel');
    if (file_exists("$PANEL_DATA/clamav/scan_results.log"))
        check_path("$PANEL_DATA/clamav/scan_results.log", 0640, 'root', 'www');
}

// mail_limits/: limit/whitelist los escribe antispam_admin. redis_pass es secreto del
// grupo maillimit y se comprueba aparte (NO tocar aquí su propietario).
if (is_dir("$PANEL_DATA/mail_limits")) {
    check_path("$PANEL_DATA/mail_limits", 02770, $PANEL_USER, 'www');
    foreach (['limit', 'whitelist'] as $ml) {
        if (file_exists("$PANEL_DATA/mail_limits/$ml"))
            check_path("$PANEL_DATA/mail_limits/$ml", 0640, $PANEL_USER, 'www');
    }
    if (file_exists("$PANEL_DATA/mail_limits/redis_pass"))
        check_path("$PANEL_DATA/mail_limits/redis_pass", 0640, 'root', 'maillimit');
}

// ssl/sencrypt: cuentas ACME y certificados los escribe Lescript (panel). Solo se
// comprueban directorios; el modo de los ficheros (private.pem 600) lo gestiona Lescript.
check_path("$PANEL_DATA/ssl", 0750, $PANEL_USER, 'www');
if (is_dir("$PANEL_DATA/ssl/sencrypt")) {
    check_path("$PANEL_DATA/ssl/sencrypt", 0750, $PANEL_USER, 'www');
    foreach (glob("$PANEL_DATA/ssl/sencrypt/*/", GLOB_ONLYDIR) ?: [] as $sd) {
        check_path(rtrim($sd, '/'), 0750, $PANEL_USER, 'www');
    }
}

// acme-challenge/: tokens HTTP-01 (el panel escribe, Apache sirve vía Alias).
check_path("$PANEL_DATA/acme-challenge", 02775, $PANEL_USER, 'www');

// updates/: lo escriben scripts root (panel_update, sys_update_check); el panel solo lee.
check_path("$PANEL_DATA/updates", 0755, 'root', 'www');
check_glob("$PANEL_DATA/updates/*", 0644, 'root', 'www');

// ── resumen ──────────────────────────────────────────────────────────────────
echo "\n" . str_repeat('═', 60) . "\n";
if ($DRY_RUN) {
    echo "  Revisión completada: $issues problema(s) encontrado(s).\n";
    echo "  Ejecuta sin --check para corregirlos.\n";
} else {
    echo "  Completado: $issues problema(s) detectado(s), $fixed correccion(es) aplicada(s).\n";
}
echo str_repeat('═', 60) . "\n\n";

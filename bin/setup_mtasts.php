#!/usr/bin/php
<?php
/**
 * setup_mtasts.php — Da de alta MTA-STS (RFC 8461) para un dominio del panel.
 *
 * MTA-STS le dice a los MTA remotos (Gmail, Microsoft — que NO usan DANE) que exijan
 * STARTTLS con certificado válido al entregar correo a este dominio. Requiere:
 *   - un host de política `mta-sts.<dominio>` servido por HTTPS con cert VÁLIDO, que
 *     publica `/.well-known/mta-sts.txt`;
 *   - un TXT `_mta-sts.<dominio>` con un id que cambia cuando cambia la política;
 *   - (recomendado) TXT `_smtp._tls.<dominio>` para reportes TLS-RPT.
 *
 * Este script automatiza TODO el montaje (antes era manual, editando la BD a mano):
 *   1. crea el subdominio `mta-sts.<dominio>` (vhost :443) apuntando al cert del panel,
 *   2. crea su webroot y escribe la política,
 *   3. añade los registros DNS (A/AAAA mta-sts, _mta-sts, _smtp._tls) a la zona,
 *   4. asegura que `mta-sts.<dominio>` está en panel_le_extra_sans (para que el cert
 *      del panel lo cubra como SAN — ver modules/sencrypt/hooks/OnDaemonHour.hook.php).
 *
 * Generalizable: sin nombres cableados. Toma el dominio del argumento o, por defecto,
 * de dns_provider_domain, y deriva todo del dominio real. Idempotente.
 *
 * Uso:  php bin/setup_mtasts.php [dominio] [enforce|testing|none]
 *       (el instalador lo llama sin argumentos tras crear la zona base)
 */

$rootPath = str_replace('\\', '/', dirname(__FILE__));
$rootPath = str_replace('/bin', '/', $rootPath);
chdir($rootPath);

require_once 'dryden/loader.inc.php';
require_once 'cnf/db.php';
require_once 'inc/dbc.inc.php';

if (!runtime_controller::IsCLI()) { exit(1); }

global $zdbh;

// ── Parámetros ───────────────────────────────────────────────────────────────
$domain = isset($argv[1]) && $argv[1] !== '' ? strtolower(trim($argv[1]))
                                              : ctrl_options::GetSystemOption('dns_provider_domain');
$mode   = isset($argv[2]) && $argv[2] !== '' ? strtolower(trim($argv[2])) : 'enforce';
if (!in_array($mode, array('enforce', 'testing', 'none'), true)) { $mode = 'enforce'; }

if (fs_director::CheckForEmptyValue($domain)) {
    fwrite(STDERR, "Sin dominio (ni argumento ni dns_provider_domain): no se monta MTA-STS.\n");
    exit(0);
}

$ip  = ctrl_options::GetSystemOption('server_ip');
$ip6 = ctrl_options::GetSystemOption('server_ip6');
$has6 = !fs_director::CheckForEmptyValue($ip6);

// ── 1. Localizar el dominio y su propietario ─────────────────────────────────
$vstmt = $zdbh->prepare("SELECT vh_id_pk, vh_acc_fk FROM x_vhosts WHERE vh_name_vc=:d AND vh_type_in=1 AND vh_deleted_ts IS NULL LIMIT 1");
$vstmt->execute([':d' => $domain]);
$parent = $vstmt->fetch(PDO::FETCH_ASSOC);
if (!$parent) {
    fwrite(STDERR, "El dominio '$domain' no existe como vhost del panel; crea la zona antes de MTA-STS.\n");
    exit(1);
}
$parentVid = (int)$parent['vh_id_pk'];
$uid = (int)$parent['vh_acc_fk'];
$owner = $zdbh->query("SELECT ac_user_vc FROM x_accounts WHERE ac_id_pk=" . $uid . " LIMIT 1")->fetchColumn();
if (!$owner) { fwrite(STDERR, "No se encuentra el propietario del dominio.\n"); exit(1); }

$stsHost     = 'mta-sts.' . $domain;
$destination = str_replace('.', '_', $stsHost);

// ── 2. Cert del panel (para el vh_ssl_tx del subdominio mta-sts) ─────────────
// El host de política DEBE presentar un cert válido; usamos el del panel, que ya
// incluye mta-sts.<dominio> como SAN (panel_le_extra_sans). fullchain.pem para enviar
// la cadena completa a los clientes (Apache no manda la cadena con SSLCACertificateFile).
$panelOwner  = 'zadmin';
$panelDomain = ctrl_options::GetSystemOption('bulwark_domain');
$certBase    = rtrim(ctrl_options::GetSystemOption('hosted_dir'), '/') . '/' . $panelOwner
             . '/ssl/sencrypt/letsencrypt/' . $panelDomain . '/';
$ssl_tx  = "# MTA-STS policy host - cert del panel (fullchain) - start\n";
$ssl_tx .= "SSLEngine On\n";
$ssl_tx .= "SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1\n";
$ssl_tx .= "SSLHonorCipherOrder on\n";
$ssl_tx .= "SSLCipherSuite \"ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305\"\n";
$ssl_tx .= "SSLCertificateFile " . $certBase . "fullchain.pem\n";
$ssl_tx .= "SSLCertificateKeyFile " . $certBase . "private.pem\n";
$ssl_tx .= "# MTA-STS policy host - cert del panel (fullchain) - end\n";

// ── 3. Subdominio mta-sts.<dominio> (vhost :443) ─────────────────────────────
$sub = $zdbh->prepare("SELECT vh_id_pk FROM x_vhosts WHERE vh_name_vc=:n AND vh_type_in=2 AND vh_deleted_ts IS NULL LIMIT 1");
$sub->execute([':n' => $stsHost]);
$subVid = $sub->fetchColumn();
if (!$subVid) {
    $ins = $zdbh->prepare("INSERT INTO x_vhosts
        (vh_acc_fk, vh_name_vc, vh_directory_vc, vh_type_in, vh_ssl_tx, vh_ssl_port_in, vh_forcessl_in, vh_created_ts)
        VALUES (:u, :n, :dir, 2, :ssl, 443, 1, :t)");
    $ins->execute([':u' => $uid, ':n' => $stsHost, ':dir' => $destination, ':ssl' => $ssl_tx, ':t' => time()]);
    $subVid = (int)$zdbh->lastInsertId();
    echo "subdominio creado: {$stsHost} (id {$subVid})\n";
} else {
    // Reafirmar el wiring del cert (idempotente): el subdominio debe servir :443 con el cert del panel.
    $zdbh->prepare("UPDATE x_vhosts SET vh_ssl_tx=:ssl, vh_ssl_port_in=443, vh_forcessl_in=1 WHERE vh_id_pk=:id")
         ->execute([':ssl' => $ssl_tx, ':id' => (int)$subVid]);
    echo "subdominio ya existe: {$stsHost} (id {$subVid}); wiring del cert reafirmado\n";
}

// ── 4. Webroot + política /.well-known/mta-sts.txt ───────────────────────────
$paths = ctrl_options::GetVhostPaths($owner, $destination);
foreach (array('domain_root', 'public_html', 'tmp', 'logs') as $p) {
    if (!empty($paths[$p])) { fs_director::CreateDirectory($paths[$p]); }
}
$wellknown = rtrim($paths['public_html'], '/') . '/.well-known';
fs_director::CreateDirectory($wellknown);

// MX de la política: se lee de la zona (registros MX del dominio), fallback mail.<dominio>.
$mxStmt = $zdbh->prepare("SELECT dn_target_vc FROM x_dns WHERE dn_vhost_fk=:v AND dn_type_vc='MX' AND dn_deleted_ts IS NULL ORDER BY dn_priority_in ASC");
$mxStmt->execute([':v' => $parentVid]);
$mxHosts = array_filter(array_map(function ($h) { return rtrim(trim($h), '.'); }, $mxStmt->fetchAll(PDO::FETCH_COLUMN)));
if (empty($mxHosts)) { $mxHosts = array('mail.' . $domain); }

$policy  = "version: STSv1\n";
$policy .= "mode: " . $mode . "\n";
foreach ($mxHosts as $mx) { $policy .= "mx: " . $mx . "\n"; }
$policy .= "max_age: 604800\n";
file_put_contents($wellknown . '/mta-sts.txt', $policy);

// Permisos: propietario del vhost, legible por el servidor web (igual que la zona base).
if (!empty($paths['domain_root'])) { fs_director::SetFileSystemPermissions($paths['domain_root'], 0755); }
echo "política escrita: " . $wellknown . "/mta-sts.txt (mode={$mode}, mx=" . implode(',', $mxHosts) . ")\n";

// ── 5. Registros DNS en la zona del dominio (idempotente) ────────────────────
$policyId = (string)time(); // el id del TXT _mta-sts cambia cuando cambia la política

// upsert por (tipo,host): actualiza el target si existe, si no inserta.
$upsert = function ($type, $host, $ttl, $target, $prio) use ($zdbh, $uid, $parentVid, $domain) {
    $sel = $zdbh->prepare("SELECT dn_id_pk FROM x_dns WHERE dn_vhost_fk=:v AND dn_type_vc=:t AND dn_host_vc=:h AND dn_deleted_ts IS NULL LIMIT 1");
    $sel->execute([':v' => $parentVid, ':t' => $type, ':h' => $host]);
    $id = $sel->fetchColumn();
    if ($id) {
        $zdbh->prepare("UPDATE x_dns SET dn_target_vc=:tg, dn_ttl_in=:ttl WHERE dn_id_pk=:id")
             ->execute([':tg' => $target, ':ttl' => $ttl, ':id' => (int)$id]);
        return 'upd';
    }
    $zdbh->prepare("INSERT INTO x_dns
        (dn_acc_fk, dn_name_vc, dn_vhost_fk, dn_type_vc, dn_host_vc, dn_ttl_in, dn_target_vc, dn_priority_in, dn_created_ts)
        VALUES (:u, :name, :v, :t, :h, :ttl, :tg, :p, :ts)")
         ->execute([':u' => $uid, ':name' => $domain, ':v' => $parentVid, ':t' => $type,
                    ':h' => $host, ':ttl' => $ttl, ':tg' => $target, ':p' => $prio, ':ts' => time()]);
    return 'ins';
};

$upsert('A',   'mta-sts', 3600, $ip, null);
if ($has6) { $upsert('AAAA', 'mta-sts', 3600, $ip6, null); }
$upsert('TXT', '_mta-sts', 3600, 'v=STSv1; id=' . $policyId, null);
$upsert('TXT', '_smtp._tls', 3600, 'v=TLSRPTv1; rua=mailto:postmaster@' . $domain, null);
echo "registros DNS MTA-STS aplicados (id política {$policyId})\n";

// ── 6. SAN del cert del panel ────────────────────────────────────────────────
// No hace falta tocar panel_le_extra_sans: el subdominio mta-sts queda cableado al cert del panel
// (su vh_ssl_tx referencia el dir del cert del panel), y el hook de emisión lo detecta por ROL y
// lo añade como SAN automáticamente. Así mta-sts no ensucia la lista editable de nombres de servicio.

// ── 7. Avisar al daemon (regenera zona + named.conf + vhost apache) ──────────
$zdbh->exec("UPDATE x_settings SET so_value_tx='true' WHERE so_name_vc='apache_changed'");
$zdbh->exec("UPDATE x_settings SET so_value_tx='true' WHERE so_name_vc='dns_hasupdates'");
echo "OK MTA-STS montado para {$domain} (ejecuta el daemon y reemite el cert del panel para servir la política).\n";

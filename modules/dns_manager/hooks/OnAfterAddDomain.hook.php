<?php

// Al añadir un dominio: crear automáticamente su zona DNS por defecto (a partir de
// la plantilla x_dns_create, con los nameservers compartidos del panel). Antes había
// que pulsar "Create default records" a mano. Idempotente: solo procesa dominios
// principales (vh_type_in=1) que aún no tengan ningún registro DNS.

CreateDefaultDNSForNewDomains();

function CreateDefaultDNSForNewDomains() {
    global $zdbh;

    // IP destino de los registros A
    $targetIP = ctrl_options::GetSystemOption('server_ip');
    if (fs_director::CheckForEmptyValue($targetIP) && isset($_SERVER['SERVER_ADDR'])) {
        $targetIP = $_SERVER['SERVER_ADDR'];
    }

    // Nameservers compartidos (fallback vanity ns1/ns2.<dominio> si no están configurados)
    $ns1cfg = ctrl_options::GetSystemOption('dns_ns1');
    $ns2cfg = ctrl_options::GetSystemOption('dns_ns2');

    // Host de correo COMPARTIDO para el MX de todos los dominios: el MX real del dominio proveedor
    // (p.ej. mail.tudominio), que es el nombre que cubre el certificado del panel. Así el MX de cada
    // cliente COINCIDE con el cert -> X509/DANE/MTA-STS válidos (antes se ponía mail.<dominio>, que
    // NO estaba en el cert -> name mismatch). Fallback: mail.<proveedor> o, sin proveedor, mail.<dominio>.
    $provider = ctrl_options::GetSystemOption('dns_provider_domain');
    $sharedMailHost = '';
    if (!fs_director::CheckForEmptyValue($provider)) {
        $mxq = $zdbh->prepare("SELECT d.dn_target_vc FROM x_dns d JOIN x_vhosts v ON v.vh_id_pk=d.dn_vhost_fk
                               WHERE v.vh_name_vc=:p AND v.vh_type_in=1 AND v.vh_deleted_ts IS NULL
                                 AND d.dn_type_vc='MX' AND d.dn_deleted_ts IS NULL ORDER BY d.dn_priority_in ASC LIMIT 1");
        $mxq->execute([':p' => $provider]);
        $sharedMailHost = rtrim((string)$mxq->fetchColumn(), '.');
        if ($sharedMailHost === '') { $sharedMailHost = 'mail.' . $provider; }
    }

    // Dominios principales activos/pendientes sin ningún registro DNS
    $sql = $zdbh->prepare("
        SELECT v.vh_id_pk, v.vh_acc_fk, v.vh_name_vc
        FROM x_vhosts v
        WHERE v.vh_type_in = 1
          AND v.vh_deleted_ts IS NULL
          AND NOT EXISTS (
              SELECT 1 FROM x_dns d
              WHERE d.dn_vhost_fk = v.vh_id_pk AND d.dn_deleted_ts IS NULL
          )
    ");
    $sql->execute();
    $domains = $sql->fetchAll();

    if (!$domains) { return; }

    $createdIds = array(); // IDs de vhost para dns_hasupdates (formato correcto: lista de IDs, no 'true')

    foreach ($domains as $dom) {
        $userID     = $dom['vh_acc_fk'];
        $domainID   = $dom['vh_id_pk'];
        $domainName = $dom['vh_name_vc'];

        $ns1 = !fs_director::CheckForEmptyValue($ns1cfg) ? $ns1cfg : ('ns1.' . $domainName);
        $ns2 = !fs_director::CheckForEmptyValue($ns2cfg) ? $ns2cfg : ('ns2.' . $domainName);

        // Plantilla: registros específicos del usuario si los tiene, si no los globales (0)
        $tpl = $zdbh->prepare("SELECT * FROM x_dns_create WHERE dc_acc_fk = :uid");
        $tpl->execute([':uid' => $userID]);
        $rows = $tpl->fetchAll();
        if (!$rows) {
            $tpl = $zdbh->query("SELECT * FROM x_dns_create WHERE dc_acc_fk = 0");
            $rows = $tpl->fetchAll();
        }

        $ins = $zdbh->prepare("INSERT INTO x_dns
            (dn_acc_fk, dn_name_vc, dn_vhost_fk, dn_type_vc, dn_host_vc, dn_ttl_in, dn_target_vc, dn_priority_in, dn_weight_in, dn_port_in, dn_created_ts)
            VALUES (:uid, :name, :vid, :type, :host, :ttl, :target, :prio, :weight, :port, :ts)");

        $mailHost = ($sharedMailHost !== '') ? $sharedMailHost : ('mail.' . $domainName);

        foreach ($rows as $r) {
            $target = str_replace(
                [':IP:', ':DOMAIN:', ':NS1:', ':NS2:', ':MAILHOST:'],
                [$targetIP, $domainName, $ns1, $ns2, $mailHost],
                (string)$r['dc_target_vc']
            );
            $ins->execute([
                ':uid'    => $userID,
                ':name'   => $domainName,
                ':vid'    => $domainID,
                ':type'   => $r['dc_type_vc'],
                ':host'   => $r['dc_host_vc'],
                ':ttl'    => $r['dc_ttl_in'],
                ':target' => $target,
                ':prio'   => !empty($r['dc_priority_in']) ? $r['dc_priority_in'] : null,
                ':weight' => !empty($r['dc_weight_in'])   ? $r['dc_weight_in']   : null,
                ':port'   => !empty($r['dc_port_in'])     ? $r['dc_port_in']     : null,
                ':ts'     => time(),
            ]);
        }

        // Placeholder DKIM: el daemon (CheckDKIMKeysHook) lo sustituye por la clave real.
        $ins->execute([
            ':uid' => $userID, ':name' => $domainName, ':vid' => $domainID,
            ':type' => 'TXT', ':host' => 'default._domainkey', ':ttl' => 3600,
            ':target' => 'PENDING', ':prio' => null, ':weight' => null, ':port' => null, ':ts' => time(),
        ]);

        $createdIds[] = (string)$domainID;
        echo "DNS: zona por defecto creada para {$domainName} (NS {$ns1}/{$ns2})" . fs_filehandler::NewLine();
    }

    // Pedir al daemon que escriba las zonas/named.conf. dns_hasupdates es una LISTA de IDs de vhost
    // (no 'true'): WriteDNSZoneRecordsHook hace explode(',') y regenera SOLO esas zonas. Con 'true'
    // no regeneraba ninguna (id 'true' no existe) -> la zona del dominio nuevo no se escribía.
    if (!empty($createdIds)) {
        $prev = array_filter(array_map('trim', explode(',', (string)ctrl_options::GetSystemOption('dns_hasupdates'))));
        $merged = array_values(array_unique(array_merge($prev, $createdIds)));
        $zdbh->prepare("UPDATE x_settings SET so_value_tx=:v WHERE so_name_vc='dns_hasupdates'")
             ->execute([':v' => implode(',', $merged)]);
    }
}

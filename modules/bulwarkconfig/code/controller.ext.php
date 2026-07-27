<?php

/**
 *
 * Bulwark - A Cross-Platform Open-Source Web Hosting Control panel.
 *
 * @package ZPanel
 * @version $Id$
 * @author Bobby Allen - ballen@bobbyallen.me
 * @copyright (c) 2008-2014 ZPanel Group - http://www.zpanelcp.com/
 * @license http://opensource.org/licenses/gpl-3.0.html GNU Public License v3
 *
 * This program (Bulwark) is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
class module_controller extends ctrl_module
{

    static $ok;

    static function getConfig()
    {
        global $zdbh;
        $currentuser = ctrl_users::GetUserDetail();
        $sql = "SELECT * FROM x_settings WHERE so_module_vc=:module AND so_usereditable_en = 'true' ORDER BY so_cleanname_vc";
        $module = ui_module::GetModuleName();
        $numrows = $zdbh->prepare($sql);
        $numrows->bindParam(':module', $module);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            $sql->bindParam(':module', $module);
            $res = array();
            $sql->execute();
            while ($rowsettings = $sql->fetch()) {
                if (ctrl_options::CheckForPredefinedOptions($rowsettings['so_defvalues_tx'])) {
                    $fieldhtml = ctrl_options::OuputSettingMenuField($rowsettings['so_name_vc'], $rowsettings['so_defvalues_tx'], $rowsettings['so_value_tx']);
                } else {
                    $fieldhtml = ctrl_options::OutputSettingTextArea($rowsettings['so_name_vc'], $rowsettings['so_value_tx']);
                }
                if (strpos(ctrl_options::OutputSettingTextArea($rowsettings['so_name_vc']),'smtp_password') !== false) {
                    $fieldhtml = '<input type="password" name="smtp_password" value="'.$rowsettings['so_value_tx'].'">';
                }
                array_push($res, array('cleanname' => ui_language::translate($rowsettings['so_cleanname_vc']),
                    'name' => $rowsettings['so_name_vc'],
                    'description' => ui_language::translate($rowsettings['so_desc_tx']),
                    'value' => $rowsettings['so_value_tx'],
                    'fieldhtml' => $fieldhtml));
            }
            return $res;
        } else {
            return false;
        }
    }

    static function getLastRunTime()
    {
        $time = ctrl_options::GetSystemOption('daemon_lastrun');
        if ($time != '0') {
            return date(ctrl_options::GetSystemOption('bulwark_df'), $time);
        } else {
            return false;
        }
    }

    static function getNextRunTime()
    {
        if (ctrl_options::GetSystemOption('daemon_lastrun') > 0) {
            $new_time = ctrl_options::GetSystemOption('daemon_lastrun') + ctrl_options::GetSystemOption('daemon_run_interval');
            return date(ctrl_options::GetSystemOption('bulwark_df'), $new_time);
        } else {
            // The default cron is set to run every 5 minutes on the 5 minute mark!
            return date(ctrl_options::GetSystemOption('bulwark_df'), ceil(time() / 300) * 300);
        }
    }

    static function getLastDayRunTime()
    {
        $time = ctrl_options::GetSystemOption('daemon_dayrun');
        if ($time != '0') {
            return date(ctrl_options::GetSystemOption('bulwark_df'), $time);
        } else {
            return false;
        }
    }

    static function getLastWeekRunTime()
    {
        $time = ctrl_options::GetSystemOption('daemon_weekrun');
        if ($time != '0') {
            return date(ctrl_options::GetSystemOption('bulwark_df'), $time);
        } else {
            return false;
        }
    }

    static function getLastMonthRunTime()
    {
        $time = ctrl_options::GetSystemOption('daemon_monthrun');
        if ($time != '0') {
            return date(ctrl_options::GetSystemOption('bulwark_df'), $time);
        } else {
            return false;
        }
    }

    static function doUpdateConfig()
    {
        global $zdbh;
        global $controller;
        runtime_csfr::Protect();
        $sql = "SELECT * FROM x_settings WHERE so_module_vc=:module AND so_usereditable_en = 'true'";
        $module = ui_module::GetModuleName();
        $numrows = $zdbh->prepare($sql);
        $numrows->bindParam(':module', $module);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            $sql->bindParam(':module', $module);
            $sql->execute();
            while ($row = $sql->fetch()) {
                if (!fs_director::CheckForEmptyValue($controller->GetControllerRequest('FORM', $row['so_name_vc']))) {
                    $updatesql = $zdbh->prepare("UPDATE x_settings SET so_value_tx = :value WHERE so_name_vc = :so_name_vc");
                    $value = $controller->GetControllerRequest('FORM', $row['so_name_vc']);
                    $updatesql->bindParam(':value', $value);
                    $updatesql->bindParam(':so_name_vc', $row['so_name_vc']);
                    $updatesql->execute();
                }
            }
            self::SetWriteApacheConfigTrue(); #bulwark apache port changed require rewrite of vhosts
        }
        self::$ok = true;
    }

    static function SetWriteApacheConfigTrue()
    {
        global $zdbh;
        $sql = $zdbh->prepare("UPDATE x_settings SET so_value_tx='true' WHERE so_name_vc='apache_changed'");
        $sql->execute();
    }

    static function doForceDaemon()
    {
        global $zdbh;
        global $controller;
        runtime_csfr::Protect();
        $formvars = $controller->GetAllControllerRequests('FORM');
        if (isset($formvars['inForceFull'])) {
            $sql = $zdbh->prepare("UPDATE x_settings set so_value_tx = '0' WHERE so_name_vc = 'daemon_lastrun'");
            $sql->execute();
            $sql = $zdbh->prepare("UPDATE x_settings set so_value_tx = '0' WHERE so_name_vc = 'daemon_dayrun'");
            $sql->execute();
            $sql = $zdbh->prepare("UPDATE x_settings set so_value_tx = '0' WHERE so_name_vc = 'daemon_weekrun'");
            $sql->execute();
            $sql = $zdbh->prepare("UPDATE x_settings set so_value_tx = '0' WHERE so_name_vc = 'daemon_monthrun'");
            $sql->execute();
        }
        self::$ok = true;
    }

    // -----------------------------------------------------------------------
    // Panel domain management
    // -----------------------------------------------------------------------

    // Prefijos reservados para infraestructura — no válidos como panel
    private static $reservedPrefixes = array(
        'ns1','ns2','ns3','ns4','mail','smtp','pop','pop3','imap',
        'ftp','www','webmail','autodiscover','autoconfig','vpn','ssh',
        'mx','mx1','mx2','api',
    );

    static function getCurrentBulwarkDomain()
    {
        return ctrl_options::GetSystemOption('bulwark_domain');
    }

    static function getBulwarkPort()
    {
        return ctrl_options::GetSystemOption('bulwark_port') ?: '80';
    }

    // Devuelve solo dominios raíz de x_vhosts pertenecientes a cuentas administradoras (grupo 1)
    private static function getRootDomains()
    {
        global $zdbh;
        $st = $zdbh->query(
            "SELECT v.vh_name_vc, v.vh_enabled_in
             FROM x_vhosts v
             INNER JOIN x_accounts a ON v.vh_acc_fk = a.ac_id_pk
             WHERE v.vh_deleted_ts IS NULL
               AND a.ac_group_fk = 1
             ORDER BY v.vh_name_vc"
        );
        $domains = array();
        while ($row = $st->fetch()) {
            $domains[] = array(
                'name'    => $row['vh_name_vc'],
                'enabled' => (int)$row['vh_enabled_in'] === 1,
            );
        }
        return $domains;
    }

    static function getRootDomainOptions()
    {
        $roots = self::getRootDomains();
        if (empty($roots)) return false;

        $current = self::getCurrentBulwarkDomain();
        $parts       = explode('.', $current);
        $currentRoot = count($parts) > 2
            ? implode('.', array_slice($parts, 1))
            : $current;

        $res = array();
        foreach ($roots as $entry) {
            $d       = $entry['name'];
            $enabled = $entry['enabled'];
            $label   = $d . ($enabled ? '' : ' (desactivado)');
            $res[] = array(
                'domain'   => $d,
                'label'    => $label,
                'selected' => ($d === $currentRoot || $d === $current)
                              ? 'selected="selected"' : '',
            );
        }
        return $res;
    }

    // Extrae el prefijo del dominio actual para pre-rellenar el campo
    static function getCurrentPrefix()
    {
        $current = self::getCurrentBulwarkDomain();
        $parts   = explode('.', $current);
        return count($parts) > 2 ? $parts[0] : '';
    }

    static function doUpdateBulwarkDomain()
    {
        global $zdbh, $controller;
        runtime_csfr::Protect();

        $prefix = strtolower(trim($controller->GetControllerRequest('FORM', 'inPanelPrefix')));
        $root   = trim($controller->GetControllerRequest('FORM', 'inRootDomain'));

        // Validar dominio raíz contra whitelist server-side
        $allowed = array_column(self::getRootDomains(), 'name');
        if (!in_array($root, $allowed, true)) return;

        // Construir el FQDN final
        if ($prefix === '') {
            $fqdn = $root;
        } else {
            // Prefijo: solo letras, números y guiones; no reservado; max 63 chars
            if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $prefix)) return;
            if (in_array($prefix, self::$reservedPrefixes, true)) return;
            $fqdn = $prefix . '.' . $root;
        }

        $st = $zdbh->prepare("UPDATE x_settings SET so_value_tx=:v WHERE so_name_vc='bulwark_domain'");
        $st->bindValue(':v', $fqdn);
        $st->execute();

        self::$ok = true;
    }

    // -----------------------------------------------------------------------
    // Panel certificate — nombres de servicio (SANs)
    // -----------------------------------------------------------------------

    // Nombres SIEMPRE incluidos por rol (solo lectura): FQDN del panel + vhosts cableados al cert
    // del panel (p.ej. mta-sts). No se editan aquí; se añaden solos.
    static function getPanelSansAuto()
    {
        global $zdbh;
        $domain = strtolower((string)ctrl_options::GetSystemOption('bulwark_domain'));
        $names  = array($domain);
        $w = $zdbh->prepare("SELECT DISTINCT LOWER(vh_name_vc) FROM x_vhosts WHERE vh_deleted_ts IS NULL AND vh_ssl_tx LIKE ?");
        $w->execute(array('%/' . $domain . '/%'));
        foreach ($w->fetchAll(PDO::FETCH_COLUMN) as $vn) { if ($vn !== '' && !in_array($vn, $names, true)) { $names[] = $vn; } }
        return htmlspecialchars(implode(', ', $names), ENT_QUOTES, 'UTF-8');
    }

    // Nombres de SERVICIO candidatos para el cert del panel: registros A/AAAA de la zona del dominio
    // proveedor que apuntan a ESTE servidor y NO son infraestructura ni tienen su propio cert. Es lo
    // que se ofrece para MARCAR (nada de escribir a mano). Excluye: nameservers (ns\d + targets NS),
    // www, el FQDN del panel, y los que ya son un vhost (apex/subdominios con su cert, y mta-sts que
    // ya se incluye solo por rol). Devuelve array de FQDN.
    static function getEligiblePanelSans()
    {
        global $zdbh;
        $prov = strtolower((string)ctrl_options::GetSystemOption('dns_provider_domain'));
        if ($prov === '') { return array(); }
        $panelFqdn = strtolower((string)ctrl_options::GetSystemOption('bulwark_domain'));
        $ips = array_values(array_filter(array(
            (string)ctrl_options::GetSystemOption('server_ip'),
            (string)ctrl_options::GetSystemOption('server_ip6'))));
        if (empty($ips)) { return array(); }
        $ph = implode(',', array_fill(0, count($ips), '?'));
        $q = $zdbh->prepare(
            "SELECT DISTINCT LOWER(d.dn_host_vc) FROM x_dns d
               JOIN x_vhosts v ON v.vh_id_pk = d.dn_vhost_fk
              WHERE v.vh_name_vc = ? AND v.vh_type_in = 1 AND v.vh_deleted_ts IS NULL
                AND d.dn_deleted_ts IS NULL AND d.dn_type_vc IN ('A','AAAA')
                AND d.dn_target_vc IN ($ph)");
        $q->execute(array_merge(array($prov), $ips));
        $exclude = array($panelFqdn => true);
        $nsq = $zdbh->prepare("SELECT DISTINCT LOWER(TRIM(TRAILING '.' FROM d.dn_target_vc)) FROM x_dns d JOIN x_vhosts v ON v.vh_id_pk=d.dn_vhost_fk WHERE v.vh_name_vc=:d AND v.vh_deleted_ts IS NULL AND d.dn_type_vc='NS' AND d.dn_deleted_ts IS NULL");
        $nsq->execute(array(':d' => $prov));
        foreach ($nsq->fetchAll(PDO::FETCH_COLUMN) as $ns) { if ($ns !== '') { $exclude[$ns] = true; } }
        $isVhost = $zdbh->prepare("SELECT COUNT(*) FROM x_vhosts WHERE vh_name_vc=? AND vh_deleted_ts IS NULL AND vh_type_in IN (1,2)");
        $out = array();
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $label) {
            $label = trim(strtolower((string)$label));
            if ($label === '' || $label === 'www' || strpos($label, '*') !== false || preg_match('/^ns[0-9]/', $label)) { continue; }
            $fqdn = ($label === '@') ? $prov : $label . '.' . $prov;
            if (isset($exclude[$fqdn])) { continue; }
            $isVhost->execute(array($fqdn));
            if ((int)$isVhost->fetchColumn() > 0) { continue; } // ya tiene su propio cert / va por rol
            if (!in_array($fqdn, $out, true)) { $out[] = $fqdn; }
        }
        sort($out);
        return $out;
    }

    // Filas de la tabla-selector: candidatos + estado (marcado si ya está en el cert del panel).
    static function getPanelSans_List()
    {
        $elig = self::getEligiblePanelSans();
        $sel  = array_filter(array_map('trim', explode(',', strtolower((string)ctrl_options::GetSystemOption('panel_le_extra_sans')))));
        if (empty($elig)) {
            return array(array('Psan_Fqdn' => ui_language::translate('No hay nombres de servicio en el DNS apuntando a este servidor. Crea el registro (p.ej. mail o ftp) en el DNS Manager y aparecerá aquí.'), 'Psan_Checkbox' => NULL));
        }
        $res = array();
        foreach ($elig as $fqdn) {
            $h = htmlspecialchars($fqdn, ENT_QUOTES, 'UTF-8');
            $chk = in_array($fqdn, $sel, true) ? ' checked' : '';
            $res[] = array('Psan_Fqdn' => $fqdn, 'Psan_Checkbox' => '<input type="checkbox" name="inPanelSans[]" value="' . $h . '"' . $chk . '>');
        }
        return $res;
    }

    // Nombres reales que cubre el certificado del panel AHORA (leídos del propio cert).
    static function getPanelCertNames()
    {
        $domain = (string)ctrl_options::GetSystemOption('bulwark_domain');
        $cert = rtrim((string)ctrl_options::GetSystemOption('hosted_dir'), '/') . '/zadmin/ssl/sencrypt/letsencrypt/' . $domain . '/cert.pem';
        if (!is_file($cert)) { return ui_language::translate('No certificate issued yet.'); }
        $info = @openssl_x509_parse((string)@file_get_contents($cert));
        if (!is_array($info) || empty($info['extensions']['subjectAltName'])) { return htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); }
        $sans = array();
        foreach (explode(',', $info['extensions']['subjectAltName']) as $s) {
            $s = trim(preg_replace('/^DNS:/i', '', trim($s)));
            if ($s !== '' && !in_array($s, $sans, true)) { $sans[] = $s; }
        }
        return htmlspecialchars(implode(', ', $sans), ENT_QUOTES, 'UTF-8');
    }

    // Guarda la SELECCIÓN de nombres del cert del panel (checkboxes). Anti-tamper: solo se aceptan
    // los que están en la lista de elegibles real (no se confía en el POST).
    static function doUpdatePanelSans()
    {
        global $zdbh;
        runtime_csfr::Protect();
        $elig = self::getEligiblePanelSans();
        $posted = (isset($_POST['inPanelSans']) && is_array($_POST['inPanelSans'])) ? $_POST['inPanelSans'] : array();
        $out = array();
        foreach ($posted as $p) {
            $p = strtolower(trim((string)$p));
            if (in_array($p, $elig, true) && !in_array($p, $out, true)) { $out[] = $p; }
        }
        sort($out);
        $val = implode(',', $out);
        $zdbh->prepare("UPDATE x_settings SET so_value_tx=:v WHERE so_name_vc='panel_le_extra_sans'")->execute(array(':v' => $val));
        self::$ok = true;
    }

    static function getResult()
    {
        if (!fs_director::CheckForEmptyValue(self::$ok)) {
            return ui_sysmessage::shout(ui_language::translate("Changes to your settings have been saved successfully!"));
        }
        return;
    }

}
<?php
/**
 * Mail Server (mailserver_admin) — Vista SOLO LECTURA del servidor de correo:
 * cola de Postfix, últimas líneas del maillog, contadores e IPs bloqueadas.
 *
 * Datos privilegiados vía privilege::run() (sin exec directo):
 *   - mail_status_dump  -> /var/bulwark/logs/mail_status.json  (cola + log + stats)
 *   - fw_status_dump    -> /var/bulwark/logs/fw_status.json    (IPs baneadas, reusado de fw_admin)
 */
class module_controller extends ctrl_module
{
    static $data = null;   // mail_status.json parseado
    static $fw   = null;   // fw_status.json parseado

    // Ejecuta los dumps privilegiados y cachea el JSON. Idempotente por request.
    private static function load()
    {
        if (self::$data !== null) { return; }
        if (!class_exists('privilege')) { require_once '/usr/local/bulwark/dryden/sys/privilege.class.php'; }

        try { privilege::run('mail_status_dump'); } catch (\Exception $e) {}
        $j = @file_get_contents('/var/bulwark/logs/mail_status.json');
        self::$data = ($j !== false) ? (json_decode($j, true) ?: array()) : array();

        try { privilege::run('fw_status_dump'); } catch (\Exception $e) {}
        $f = @file_get_contents(ctrl_options::GetSystemOption('fw_status_json_path') ?: '/var/bulwark/logs/fw_status.json');
        self::$fw = ($f !== false) ? (json_decode($f, true) ?: array()) : array();
    }

    static function getInit()
    {
        global $controller;
        return '<link rel="stylesheet" type="text/css" href="modules/' . $controller->GetControllerRequest('URL', 'module') . '/assets/mailserver.css">';
    }

    // ---- Contadores (tokens simples) --------------------------------------
    private static function stat($k) { self::load(); return (int)(self::$data['stats'][$k] ?? 0); }
    static function getStatSent()       { return self::stat('sent'); }
    static function getStatDeferred()   { return self::stat('deferred'); }
    static function getStatBounced()    { return self::stat('bounced'); }
    static function getStatRejected()   { return self::stat('rejected'); }
    static function getStatSaslFailed() { return self::stat('sasl_failed'); }
    static function getQueueCount()     { self::load(); return (int)(self::$data['queue_count'] ?? 0); }

    static function getGeneratedTime()
    {
        self::load();
        $ts = (int)(self::$data['ts'] ?? 0);
        return $ts ? date(ctrl_options::GetSystemOption('bulwark_df') ?: 'Y-m-d H:i:s', $ts) : '—';
    }

    // ---- Cola de correo (loop) --------------------------------------------
    static function getMail_Queue()
    {
        self::load();
        $q = self::$data['queue'] ?? array();
        if (empty($q)) {
            return array(array('Q_Id' => ui_language::translate('Queue is empty'), 'Q_Queue' => '', 'Q_Time' => '', 'Q_Sender' => '', 'Q_Recipients' => '', 'Q_Size' => '', 'Q_Reason' => ''));
        }
        $rows = array();
        foreach ($q as $m) {
            $rcpts = array();
            $reason = '';
            foreach (($m['recipients'] ?? array()) as $r) {
                if (!empty($r['address'])) { $rcpts[] = $r['address']; }
                if (empty($reason) && !empty($r['delay_reason'])) { $reason = $r['delay_reason']; }
            }
            $sz = (int)($m['message_size'] ?? 0);
            $rows[] = array(
                'Q_Id'         => (string)($m['queue_id'] ?? '?'),
                'Q_Queue'      => (string)($m['queue_name'] ?? ''),
                'Q_Time'       => !empty($m['arrival_time']) ? date('Y-m-d H:i', (int)$m['arrival_time']) : '',
                'Q_Sender'     => (string)($m['sender'] ?? ''),
                'Q_Recipients' => implode(', ', $rcpts),
                'Q_Size'       => $sz > 1024 ? round($sz / 1024) . ' KB' : $sz . ' B',
                'Q_Reason'     => $reason,
            );
        }
        return $rows;
    }

    // ---- Log reciente (bloque <pre>, escapado) ----------------------------
    static function getMailLogHtml()
    {
        self::load();
        $lines = self::$data['log'] ?? array();
        if (empty($lines)) { return '<span class="ms-muted">' . ui_language::translate('No recent log entries.') . '</span>'; }
        $out = array();
        // Mostrar las más recientes arriba
        foreach (array_reverse($lines) as $l) {
            $h = htmlspecialchars((string)$l, ENT_QUOTES, 'UTF-8');
            $cls = '';
            if (stripos($l, 'status=sent') !== false)      { $cls = 'ms-ok'; }
            elseif (stripos($l, 'reject') !== false || stripos($l, 'status=bounced') !== false) { $cls = 'ms-bad'; }
            elseif (stripos($l, 'authentication failed') !== false || stripos($l, 'status=deferred') !== false) { $cls = 'ms-warn'; }
            $out[] = '<div class="ms-logline ' . $cls . '">' . $h . '</div>';
        }
        return implode('', $out);
    }

    // ---- IPs bloqueadas (loop, reusa fw_status.json) ----------------------
    static function getBanned_IPs()
    {
        self::load();
        $rows = array();
        foreach ((array)(self::$fw['sshguard'] ?? array()) as $ip)        { $rows[] = array('B_Ip' => $ip, 'B_Source' => 'SSHGuard (auto)'); }
        foreach ((array)(self::$fw['bulwark_blocked'] ?? array()) as $ip) { $rows[] = array('B_Ip' => $ip, 'B_Source' => ui_language::translate('Manual / panel')); }
        if (empty($rows)) { $rows[] = array('B_Ip' => ui_language::translate('No blocked addresses'), 'B_Source' => ''); }
        return $rows;
    }

    static function getBannedCount()
    {
        self::load();
        return count((array)(self::$fw['sshguard'] ?? array())) + count((array)(self::$fw['bulwark_blocked'] ?? array()));
    }
}

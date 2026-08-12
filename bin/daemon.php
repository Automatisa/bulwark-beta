<?php

/**
 * @copyright 2014-2023 Sentora Project (http://www.sentora.org/) 
 * @copyright 2024-present Bulwark / Automatisa (GPLv3 fork of Sentora)
 * Sentora is a GPL fork of the ZPanel Project whose original header follows:
 *
 * The daemon initiator file.
 * @package zpanelx
 * @subpackage core -> daemon
 * @author Bobby Allen (ballen@bobbyallen.me)
 * @copyright ZPanel Project (http://www.zpanelcp.com/)
 * @link http://www.zpanelcp.com/
 * @license GPL (http://www.gnu.org/licenses/gpl.html)
 *
 * Changes P.Peyremorte
 * - added timestamp of begin and end of dameon run for logfile
 * - corrected OnDaemonHour that occured on each cron run (5 min,)
 */
set_time_limit(0);

$rawPath = str_replace("\\", "/", dirname(__FILE__));
$rootPath = str_replace("/bin", "/", $rawPath);
chdir($rootPath);

require_once 'dryden/loader.inc.php';
require_once 'cnf/db.php';
require_once 'inc/dbc.inc.php';

$daemonLog = new debug_logger();
$daemonLog->method = "file";
$daemonLog->logcode = "001";

$dateformat = ctrl_options::GetSystemOption('Bulwark_df');

if (!runtime_controller::IsCLI())
    echo "<pre>";
echo "Daemon is now running... (".date($dateformat).")\n";

$daemonLog->detail = "Daemon execution started...";
$daemonLog->writeLog();

runtime_hook::Execute("OnStartDaemonRun");
runtime_hook::Execute("OnDaemonRun");
runtime_hook::Execute("OnEndDaemonRun");

// Compuertas temporales: cada ciclo usa su opción de x_settings como marca de última
// ejecución. El instalador debe sembrarlas, pero si alguna falta (histórico:
// daemon_hourrun nunca se sembró) GetSystemOption devuelve false, la comparación
// (time() >= false + N) pasa en CADA tick del cron y SetSystemOption(create=false)
// solo hace UPDATE -> la fila jamás se crea y el ciclo se ejecuta cada 5 minutos.
// Auto-reparación: sembrar con 0 las que falten (comportamiento previsto del setter).
foreach (array('daemon_hourrun', 'daemon_dayrun', 'daemon_weekrun', 'daemon_monthrun') as $gate) {
    if (ctrl_options::GetSystemOption($gate) === false) {
        ctrl_options::SetSystemOption($gate, '0', true);
    }
}

if (time() >= ctrl_options::GetSystemOption('daemon_hourrun') + 3600) {
    ctrl_options::SetSystemOption('daemon_hourrun', time());
    runtime_hook::Execute("OnStartDaemonHour");
    runtime_hook::Execute("OnDaemonHour");
    runtime_hook::Execute("OnEndDaemonHour");
}

if (time() >= ctrl_options::GetSystemOption('daemon_dayrun') + 24*3600) {
    ctrl_options::SetSystemOption('daemon_dayrun', time());
    runtime_hook::Execute("OnStartDaemonDay");
    runtime_hook::Execute("OnDaemonDay");
    runtime_hook::Execute("OnEndDaemonDay");
}

if (time() >= ctrl_options::GetSystemOption('daemon_weekrun') + 7*24*3600) {
    ctrl_options::SetSystemOption('daemon_weekrun', time());
    runtime_hook::Execute("OnStartDaemonWeek");
    runtime_hook::Execute("OnDaemonWeek");
    runtime_hook::Execute("OnEndDaemonWeek");
}

if (time() >= ctrl_options::GetSystemOption('daemon_monthrun') + 30*24*3600) {
    ctrl_options::SetSystemOption('daemon_monthrun', time());
    runtime_hook::Execute("OnStartDaemonMonth");
    runtime_hook::Execute("OnDaemonMonth");
    runtime_hook::Execute("OnEndDaemonMonth");
}
echo "\nDaemon run complete! (" . date($dateformat) . ")\n";

ctrl_options::SetSystemOption('daemon_lastrun', time());

$daemonLog->detail = "Daemon execution completed!";
$daemonLog->writeLog();

if (!runtime_controller::IsCLI())
    echo "</pre>";
exit;

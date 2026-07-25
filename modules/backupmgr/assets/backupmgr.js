/* backupmgr.js — externalizado del <script> inline (CSP Fase 2). */
(function () {
    "use strict";
    // Activar la pestaña indicada por ?tab=xxx (para volver a la pestaña correcta
    // tras guardar/probar/borrar o al paginar el registro).
    var m = window.location.search.match(/[?&]tab=([a-z]+)/);
    var tab = m ? m[1] : null;
    var map = { backups: '#bk-backups', auto: '#bk-auto', conn: '#bk-conn', log: '#bk-log' };
    if (tab && map[tab]) {
        var el = document.querySelector('#bkTabs a[href="' + map[tab] + '"]');
        if (el) el.click();
    }
})();

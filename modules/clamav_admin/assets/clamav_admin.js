/* clamav_admin.js — externalizado del <script> inline (CSP Fase 2). */
(function () {
    "use strict";

    var tabMap = {
        status:     '#cv-status',
        email:      '#cv-email',
        scan:       '#cv-scan',
        results:    '#cv-results',
        quarantine:       '#cv-quarantine',
        updates:          '#cv-updates',
        restorereq:       '#cv-restore-requests'
    };

    var cvTabs  = document.getElementById('cvTabs');
    var phpTab  = cvTabs ? cvTabs.getAttribute('data-active-tab') : '';
    var urlMatch = window.location.search.match(/[?&]tab=([a-z]+)/);
    var target   = (phpTab && tabMap[phpTab]) ? phpTab
                 : (urlMatch && tabMap[urlMatch[1]] ? urlMatch[1] : '');
    if (target) {
        $(function () {
            $('#cvTabs a[href="' + tabMap[target] + '"]').tab('show');
        });
    }

}());

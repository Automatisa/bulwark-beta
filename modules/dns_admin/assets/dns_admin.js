/* dns_admin.js — init de pestañas (CSP Fase 2). */
$(document).ready(function() {
        $('#dnsTabs').tabs({
            cookie: { expires: 7, name: "dnsTabs Cookie" }
        });
    });

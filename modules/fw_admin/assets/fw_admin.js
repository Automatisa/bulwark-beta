/*
 * fw_admin.js — JS del módulo Firewall, externalizado del <script> inline de module.zpm
 * para poder retirar 'unsafe-inline' (CSP Fase 2). Las acciones de tabla se disparan por
 * delegación desde atributos data-fw / data-id / data-ip / data-args (sin manejadores inline).
 */
(function () {
    "use strict";

    var tabMap = {
        status: '#fw-status', blocked: '#fw-blocked', whitelist: '#fw-whitelist',
        sshguard: '#fw-sshguard', loginattempts: '#fw-loginattempts', rules: '#fw-rules', config: '#fw-config'
    };
    function clickTab(key) {
        var sel = tabMap[key]; if (!sel) return;
        var link = document.querySelector('#fwTabs a[href="' + sel + '"]');
        if (link) link.click();
    }

    // Acciones de tabla (envían formularios ocultos; cada una hace su propio confirm).
    function fwDeleteBlock(id, ip) {
        if (!confirm('¿Eliminar bloqueo de ' + ip + '?')) return;
        document.getElementById('fwDeleteBlockID').value = id;
        document.getElementById('fwDeleteBlockForm').submit();
    }
    function fwDeleteWhite(id, ip) {
        if (!confirm('¿Eliminar ' + ip + ' de la lista blanca?')) return;
        document.getElementById('fwDeleteWhiteID').value = id;
        document.getElementById('fwDeleteWhiteForm').submit();
    }
    function fwUnban(ip) {
        if (!confirm('¿Desbanear ' + ip + ' de SSHGuard?\n\nSSHGuard puede volver a banearla si los ataques continúan.')) return;
        document.getElementById('fwUnbanIP').value = ip;
        document.getElementById('fwUnbanForm').submit();
    }
    function fwDeleteRule(id) {
        if (!confirm('¿Eliminar esta regla pf?')) return;
        document.getElementById('fwDeleteRuleID').value = id;
        document.getElementById('fwDeleteRuleForm').submit();
    }
    function fwToggleRule(id) {
        document.getElementById('fwToggleRuleID').value = id;
        document.getElementById('fwToggleRuleForm').submit();
    }
    function fwEditRule(id, action, direction, proto, src, port, portMax, desc, order) {
        document.getElementById('editRuleID').value = id;
        document.getElementById('editRulePort').value = port;
        document.getElementById('editRulePortMax').value = portMax;
        document.getElementById('editRuleDesc').value = desc;
        document.getElementById('editRuleOrder').value = order;
        function setSelect(elemId, val) {
            var sel = document.getElementById(elemId);
            for (var i = 0; i < sel.options.length; i++) {
                if (sel.options[i].value === val) { sel.selectedIndex = i; break; }
            }
        }
        setSelect('editRuleAction', action);
        setSelect('editRuleDirection', direction);
        setSelect('editRuleProto', proto);
        document.getElementById('editRuleSrc').value = (src === 'any') ? '' : src;
        jQuery('#fwEditRuleModal').modal('show');
    }

    function parseArgs(el) {
        try { var v = JSON.parse(el.getAttribute('data-args') || '[]'); return Array.isArray(v) ? v : [v]; }
        catch (e) { return []; }
    }

    document.addEventListener('click', function (ev) {
        // Enlaces que abren pestaña.
        var tabEl = ev.target.closest('[data-fw-tab]');
        if (tabEl) { ev.preventDefault(); clickTab(tabEl.getAttribute('data-fw-tab')); return; }

        // Acciones de tabla.
        var el = ev.target.closest('[data-fw]');
        if (!el) return;
        var id = el.getAttribute('data-id');
        var ip = el.getAttribute('data-ip');
        switch (el.getAttribute('data-fw')) {
            case 'deleteBlock': fwDeleteBlock(id, ip); break;
            case 'deleteWhite': fwDeleteWhite(id, ip); break;
            case 'unban': fwUnban(ip); break;
            case 'toggleRule': fwToggleRule(id); break;
            case 'deleteRule': fwDeleteRule(id); break;
            case 'editRule': fwEditRule.apply(null, parseArgs(el)); break;
        }
    });

    function onReady() {
        var m = window.location.search.match(/[?&]tab=([a-z]+)/);
        if (m && tabMap[m[1]]) clickTab(m[1]);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', onReady);
    else onReady();
})();

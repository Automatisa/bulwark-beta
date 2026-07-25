/*
 * api_manager.js — JS del módulo API Manager, externalizado del <script> inline de module.zpm
 * para poder retirar 'unsafe-inline' de script-src (CSP Fase 2). Se auto-cablea por
 * addEventListener (sin manejadores inline). copyApiToken queda global para data-call (csp-shim).
 */
function apiFilterTable(input, tableId, colIndex) {
    var filter = input.value.toLowerCase();
    var tbl = document.getElementById(tableId);
    if (!tbl || !tbl.tBodies[0]) return;
    var rows = tbl.tBodies[0].rows;
    for (var i = 0; i < rows.length; i++) {
        var cell = rows[i].cells[colIndex];
        var text = cell ? (cell.textContent || cell.innerText) : '';
        rows[i].style.display = text.toLowerCase().indexOf(filter) > -1 ? '' : 'none';
    }
}

function copyApiToken() {
    var el = document.getElementById('api-token-display');
    if (!el) return;
    var token = el.textContent.trim();
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(token).then(function () { alert('Token copiado al portapapeles.'); });
    } else {
        var ta = document.createElement('textarea');
        ta.value = token; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.focus(); ta.select();
        document.execCommand('copy'); document.body.removeChild(ta);
        alert('Token copiado al portapapeles.');
    }
}

function checkUnboundWarning() {
    var userEl = document.getElementById('inTokenUser');
    var scopeEl = document.getElementById('inTokenScope');
    var warn = document.getElementById('unbound-warning');
    if (!userEl || !warn) return;
    var scope = scopeEl ? scopeEl.value : 'read';
    warn.style.display = (userEl.value.trim() === '' && scope !== 'read') ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', function () {
    // Filtros de tabla: <input data-filter-table="tbl-x" [data-filter-col="0"]>.
    document.querySelectorAll('[data-filter-table]').forEach(function (inp) {
        inp.addEventListener('input', function () {
            apiFilterTable(inp, inp.getAttribute('data-filter-table'), parseInt(inp.getAttribute('data-filter-col') || '0', 10));
        });
    });
    // Aviso de token "sin usuario": recalcular al cambiar usuario o scope.
    var userEl = document.getElementById('inTokenUser');
    if (userEl) userEl.addEventListener('input', checkUnboundWarning);
    var scopeSel = document.getElementById('inTokenScope');
    if (scopeSel) scopeSel.addEventListener('change', checkUnboundWarning);
});

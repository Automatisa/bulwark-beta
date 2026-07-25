/* antispam_admin.js — externalizado del <script> inline de module.zpm (CSP Fase 2). */
(function () {
    "use strict";

    // ---- Activar pestaña: PHP tiene prioridad (data-active-tab), luego URL ?tab=xxx ----
    var tabMap = {
        status:    '#as-status',
        history:   '#as-history',
        whitelist: '#as-whitelist',
        blacklist: '#as-blacklist',
        greylist:  '#as-greylist',
        spamhaus:  '#as-spamhaus',
        test:      '#as-test',
        phishing:  '#as-phishing',
        config:    '#as-config'
    };
    var asTabs   = document.getElementById('asTabs');
    var phpTab   = asTabs ? asTabs.getAttribute('data-active-tab') : '';
    var urlMatch = window.location.search.match(/[?&]tab=([a-z]+)/);
    var target   = (phpTab && tabMap[phpTab]) ? phpTab : (urlMatch && tabMap[urlMatch[1]] ? urlMatch[1] : '');
    if (target) {
        $(function () {
            $('#asTabs a[href="' + tabMap[target] + '"]').tab('show');
        });
    }

    // ---- Historial: buscador + paginación ----
    var AS_PAGE_SIZE = 25;
    var asPage       = 1;
    var asFiltered   = [];

    function asAllRows() {
        return Array.prototype.slice.call(
            document.querySelectorAll('#asHistoryBody tr')
        );
    }

    function asApplyFilter(q) {
        q = q.toLowerCase().trim();
        asFiltered = asAllRows().filter(function (tr) {
            return !q || tr.textContent.toLowerCase().indexOf(q) !== -1;
        });
        asPage = 1;
        asRender();
    }

    function asRender() {
        var rows      = asAllRows();
        var total     = asFiltered.length;
        var pages     = Math.max(1, Math.ceil(total / AS_PAGE_SIZE));
        if (asPage > pages) asPage = pages;
        var start     = (asPage - 1) * AS_PAGE_SIZE;
        var end       = start + AS_PAGE_SIZE;
        var inPage    = {};
        asFiltered.forEach(function (tr, i) { inPage[i] = (i >= start && i < end); });

        // Mostrar/ocultar filas
        var fi = 0;
        rows.forEach(function (tr) {
            var idx = asFiltered.indexOf(tr);
            if (idx === -1) {
                tr.style.display = 'none';
            } else {
                tr.style.display = (idx >= start && idx < end) ? '' : 'none';
            }
        });

        // Contador
        var cnt = document.getElementById('asHistoryCount');
        if (cnt) {
            var q = document.getElementById('asHistorySearch').value.trim();
            cnt.textContent = q
                ? total + ' de ' + rows.length + ' mensajes'
                : rows.length + ' mensajes';
        }

        // Paginador Bootstrap
        var pager = document.getElementById('asHistoryPager');
        if (!pager) return;
        if (pages <= 1) { pager.innerHTML = ''; return; }

        var html = '<ul class="pagination pagination-sm" style="margin:0;">';
        html += '<li class="' + (asPage === 1 ? 'disabled' : '') + '">'
              + '<a href="#" data-as-page="' + (asPage-1) + '">&laquo;</a></li>';

        // Ventana de páginas: máximo 7 botones
        var win = 3;
        var lo  = Math.max(1, asPage - win);
        var hi  = Math.min(pages, asPage + win);
        if (lo > 1)     html += '<li class="disabled"><a>…</a></li>';
        for (var p = lo; p <= hi; p++) {
            html += '<li class="' + (p === asPage ? 'active' : '') + '">'
                  + '<a href="#" data-as-page="' + p + '">' + p + '</a></li>';
        }
        if (hi < pages) html += '<li class="disabled"><a>…</a></li>';

        html += '<li class="' + (asPage === pages ? 'disabled' : '') + '">'
              + '<a href="#" data-as-page="' + (asPage+1) + '">&raquo;</a></li>';
        html += '</ul>';
        pager.innerHTML = html;
    }

    window.asGoPage = function (p) {
        var pages = Math.max(1, Math.ceil(asFiltered.length / AS_PAGE_SIZE));
        asPage = Math.max(1, Math.min(p, pages));
        asRender();
    };

    window.asFilterHistory = function (q) {
        asApplyFilter(q);
    };

    function asInit() {
        asFiltered = asAllRows();
        asRender();
    }

    // Inicializar al activar la pestaña
    document.querySelector('#asTabs a[href="#as-history"]').addEventListener('shown.bs.tab', function () {
        asInit();
    });
    if (window.location.search.indexOf('tab=history') !== -1) {
        asInit();
    }

    // ---- Cuentas exentas de mail(): buscador + paginación (client-side) ----
    var ML_PAGE_SIZE = 10;
    var mlPage = 1;
    function mlAllRows() {
        return Array.prototype.slice.call(document.querySelectorAll('#mlExemptBody .mlExemptRow'));
    }
    window.mlExemptRender = function () {
        var body = document.getElementById('mlExemptBody');
        if (!body) return;
        var rows   = mlAllRows();
        var search = document.getElementById('mlExemptSearch');
        var q      = search ? search.value.toLowerCase().trim() : '';
        if (q !== window._mlLastQ) { mlPage = 1; window._mlLastQ = q; }
        var filtered = rows.filter(function (tr) { return !q || tr.textContent.toLowerCase().indexOf(q) !== -1; });
        var total = filtered.length;
        var pages = Math.max(1, Math.ceil(total / ML_PAGE_SIZE));
        if (mlPage > pages) mlPage = pages;
        var start = (mlPage - 1) * ML_PAGE_SIZE, end = start + ML_PAGE_SIZE;
        rows.forEach(function (tr) {
            var idx = filtered.indexOf(tr);
            tr.style.display = (idx >= start && idx < end) ? '' : 'none';
        });
        // El buscador solo aparece si hay más de una página de cuentas.
        if (search) search.style.display = (rows.length > ML_PAGE_SIZE) ? '' : 'none';
        var cnt = document.getElementById('mlExemptCount');
        if (cnt) cnt.textContent = q ? ('Mostrando ' + total + ' de ' + rows.length + ' cuentas') : '';
        var pager = document.getElementById('mlExemptPager');
        if (!pager) return;
        if (pages <= 1) { pager.innerHTML = ''; return; }
        function item(cls, label, target, disabled) {
            return '<li class="page-item ' + cls + (disabled ? ' disabled' : '') + '">'
                 + '<a class="page-link" href="#"' + (target !== null ? ' data-ml-page="' + target + '"' : '') + '>' + label + '</a></li>';
        }
        var html = '<nav aria-label="Paginación cuentas exentas"><ul class="pagination pagination-sm" style="margin:0;">';
        html += item('', '«', mlPage - 1, mlPage === 1);
        var win = 2, lo = Math.max(1, mlPage - win), hi = Math.min(pages, mlPage + win);
        if (lo > 1) {
            html += item('', '1', 1, false);
            if (lo > 2) html += item('disabled', '…', null, true);
        }
        for (var p = lo; p <= hi; p++) html += item(p === mlPage ? 'active' : '', p, p, false);
        if (hi < pages) {
            if (hi < pages - 1) html += item('disabled', '…', null, true);
            html += item('', pages, pages, false);
        }
        html += item('', '»', mlPage + 1, mlPage === pages);
        html += '</ul></nav>';
        pager.innerHTML = html;
    };
    window.mlExemptGoPage = function (p) {
        var pages = Math.max(1, Math.ceil(mlAllRows().length / ML_PAGE_SIZE));
        mlPage = Math.max(1, Math.min(p, pages));
        window.mlExemptRender();
    };
    if (document.getElementById('mlExemptBody')) window.mlExemptRender();
    var mlCfgTab = document.querySelector('#asTabs a[href="#as-config"]');
    if (mlCfgTab) mlCfgTab.addEventListener('shown.bs.tab', function () { mlPage = 1; window.mlExemptRender(); });

})();

/* CSP Fase 2: delegación de paginación y de los buscadores (sin handlers inline) */
document.addEventListener('click', function (ev) {
    var a = ev.target.closest('[data-as-page]');
    if (a) { ev.preventDefault(); asGoPage(parseInt(a.getAttribute('data-as-page'), 10)); return; }
    var m = ev.target.closest('[data-ml-page]');
    if (m) { ev.preventDefault(); mlExemptGoPage(parseInt(m.getAttribute('data-ml-page'), 10)); }
});
document.addEventListener('DOMContentLoaded', function () {
    var hs = document.getElementById('asHistorySearch');
    if (hs) hs.addEventListener('input', function () { asFilterHistory(this.value); });
    var me = document.getElementById('mlExemptSearch');
    if (me) me.addEventListener('keyup', mlExemptRender);
});


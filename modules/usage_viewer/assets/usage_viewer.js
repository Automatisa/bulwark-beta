/*
 * usage_viewer.js — JS del módulo Usage Viewer, externalizado de los <script> inline del
 * controlador para poder retirar 'unsafe-inline' (CSP Fase 2). Los datos (meses/consumo/año)
 * llegan por una isla JSON <script type="application/json" id="zpx-data"> (no ejecutable),
 * en lugar de interpolarse en el JS. Se auto-cablea por addEventListener.
 */
(function () {
    function init() {
        // --- Gráficas (Chart.js) a partir de la isla de datos ---
        var island = document.getElementById('zpx-data');
        if (island && typeof Chart !== 'undefined' && document.getElementById('zpx_bw_chart')) {
            var cfg = {};
            try { cfg = JSON.parse(island.textContent || '{}'); } catch (e) { cfg = {}; }
            var zpxM = cfg.months || [];
            var zpxD = cfg.data || {};
            var zpxO = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { maxTicksLimit: 5 } } } };
            var zpxBw = new Chart(document.getElementById('zpx_bw_chart'), { type: 'bar', data: { labels: zpxM, datasets: [{ label: 'MB', data: [], backgroundColor: '#1a4e84', borderRadius: 3 }] }, options: zpxO });
            var zpxDk = new Chart(document.getElementById('zpx_disk_chart'), { type: 'bar', data: { labels: zpxM, datasets: [{ label: 'MB', data: [], backgroundColor: '#27ae60', borderRadius: 3 }] }, options: zpxO });
            var zpxUpd = function (y) {
                var d = zpxD[y] || Array(12).fill({ bw: 0, disk: 0 });
                zpxBw.data.datasets[0].data = d.map(function (m) { return m.bw; });
                zpxDk.data.datasets[0].data = d.map(function (m) { return m.disk; });
                zpxBw.update(); zpxDk.update();
            };
            var sel = document.getElementById('zpx_year_sel');
            if (sel) sel.addEventListener('change', function () { zpxUpd(this.value); });
            if (cfg.defaultYear != null) zpxUpd(String(cfg.defaultYear));
        }

        // --- Tabla ordenable/filtrable ---
        var zpxDir = {};
        function zpxSort(col) {
            var tb = document.querySelector('#zpx_tc tbody');
            if (!tb) return;
            var rows = Array.from(tb.querySelectorAll('tr'));
            zpxDir[col] = !zpxDir[col];
            rows.sort(function (a, b) {
                var av = a.cells[col].dataset.val || a.cells[col].innerText.trim();
                var bv = b.cells[col].dataset.val || b.cells[col].innerText.trim();
                var n = parseFloat(av) - parseFloat(bv);
                var s = av.localeCompare(bv);
                return (isNaN(n) ? s : n) * (zpxDir[col] ? 1 : -1);
            });
            rows.forEach(function (r, i) { r.cells[0].innerText = i + 1; tb.appendChild(r); });
        }
        function zpxFilter() {
            var s = document.getElementById('zpx_search');
            if (!s) return;
            var q = s.value.toLowerCase();
            document.querySelectorAll('#zpx_tc tbody tr').forEach(function (r) {
                r.style.display = r.cells[1].innerText.toLowerCase().indexOf(q) > -1 ? '' : 'none';
            });
        }
        document.querySelectorAll('#zpx_tc thead th[data-sort]').forEach(function (th) {
            th.addEventListener('click', function () { zpxSort(parseInt(th.getAttribute('data-sort'), 10)); });
        });
        var search = document.getElementById('zpx_search');
        if (search) search.addEventListener('input', zpxFilter);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();

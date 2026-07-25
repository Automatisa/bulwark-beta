/*
 * cron.js — JS del módulo Cron, externalizado del <script> inline del controlador para poder
 * retirar 'unsafe-inline' (CSP Fase 2). base (ruta) y dowNames (días) llegan por una isla JSON
 * (#cron-data). Se auto-cablea: cronMode al cambiar #inSchedMode; cronPreview en los campos
 * marcados con data-cron-preview.
 */
(function () {
    var base = '', dowNames = [];
    function two(n) { return (n < 10 ? '0' : '') + n; }

    function cronMode() {
        var m = document.getElementById('inSchedMode').value;
        function show(id, on) { var e = document.getElementById(id); if (e) e.style.display = on ? 'block' : 'none'; }
        show('sched_simple', m === 'simple');
        show('sched_time', m === 'daily' || m === 'weekly' || m === 'monthly');
        show('sched_weekday', m === 'weekly');
        show('sched_monthday', m === 'monthly');
        show('sched_advanced', m === 'advanced');
        cronPreview();
    }

    function cronPreview() {
        var f = document.getElementById('cronCreateForm'); if (!f) return;
        var dom = f.inDomain.value, p = (f.inPath.value || '').replace(/\\/g, '/').replace(/^\/+/, '');
        var rel = p ? (dom ? 'web/' + dom + '/' + p : p) : '';
        document.getElementById('cronFull').textContent = base + rel;
        var m = f.inSchedMode.value, expr = '', human = '';
        function gv(n) { return f[n] ? parseInt(f[n].value, 10) : 0; }
        if (m === 'simple') { expr = f.inTimingPreset.value; human = f.inTimingPreset.options[f.inTimingPreset.selectedIndex].text; }
        else if (m === 'daily') { var h = gv('inHour'), mi = gv('inMinute'); expr = mi + ' ' + h + ' * * *'; human = 'Cada día a las ' + two(h) + ':' + two(mi); }
        else if (m === 'weekly') { var h = gv('inHour'), mi = gv('inMinute'), w = gv('inWeekday'); expr = mi + ' ' + h + ' * * ' + w; human = 'Cada ' + dowNames[w] + ' a las ' + two(h) + ':' + two(mi); }
        else if (m === 'monthly') { var h = gv('inHour'), mi = gv('inMinute'), d = gv('inMonthday'); expr = mi + ' ' + h + ' ' + d + ' * *'; human = 'El día ' + d + ' de cada mes a las ' + two(h) + ':' + two(mi); }
        else if (m === 'advanced') { expr = (f.inCronExpr.value || '').trim(); human = 'Expresión personalizada'; }
        document.getElementById('cronExpr').textContent = expr;
        document.getElementById('cronHuman').textContent = human;
    }

    function init() {
        var island = document.getElementById('cron-data');
        if (island) { try { var c = JSON.parse(island.textContent || '{}'); base = c.base || ''; dowNames = c.dow || []; } catch (e) {} }
        var mode = document.getElementById('inSchedMode');
        if (mode) mode.addEventListener('change', cronMode);
        document.querySelectorAll('[data-cron-preview]').forEach(function (el) {
            el.addEventListener('change', cronPreview);
            el.addEventListener('input', cronPreview);
        });
        if (document.getElementById('inSchedMode')) cronMode();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();

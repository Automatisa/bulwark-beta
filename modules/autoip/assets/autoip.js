/* autoip.js — rellenar el campo de IP manual con la IP sugerida (CSP Fase 2). */
document.addEventListener('click', function (ev) {
    var el = ev.target.closest('[data-fill-ip]');
    if (!el) return;
    var f = document.getElementById('inManualIP');
    if (f) { f.value = el.getAttribute('data-fill-ip'); f.style.background = '#ffffcc'; f.focus(); }
});

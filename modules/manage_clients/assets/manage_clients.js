/* manage_clients.js — abre la pestaña de alta si el formulario viene repoblado
 * tras un alta fallida (externalizado del <script> inline, CSP Fase 2). */
// Si un alta falló, el formulario viene repoblado (inNewUserName con valor): abrir esa pestaña
// para que el usuario vea el error y sus datos, en vez de aterrizar en la lista.
window.addEventListener('load', function () {
    var u = document.getElementById('inNewUserName');
    if (u && u.value.trim() !== '') {
        var t = document.querySelector('a[href="#mc-create"]');
        if (t) { try { new bootstrap.Tab(t).show(); } catch (e) { t.click(); } }
    }
});

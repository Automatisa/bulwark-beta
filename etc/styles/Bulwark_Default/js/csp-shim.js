/*
 * csp-shim.js — Delegación de eventos para eliminar los manejadores inline (onclick/onsubmit…)
 * del tema legacy y poder retirar 'unsafe-inline' de script-src (CSP, Fase 2 — ver csp_panel.md).
 *
 * Sustituye los patrones inline por atributos data-* + listeners delegados (un solo listener en
 * document, funciona con contenido añadido dinámicamente):
 *   onclick="window.location.href='X';return false;"  ->  data-href="X"
 *   onclick="return confirm('msg')"                    ->  data-confirm="msg"  (en <a>)
 *   onsubmit="return confirm('msg')"                   ->  data-confirm="msg"  (en <form>)
 *   onclick="fn('a','b')"                              ->  data-call="fn" data-args='["a","b"]'
 *
 * data-call resuelve la función por nombre en Bulwark.actions (registro) o en window (funciones
 * globales del módulo). Sin eval: los args se leen de un JSON en data-args.
 */
(function () {
    "use strict";

    window.Bulwark = window.Bulwark || {};
    Bulwark.actions = Bulwark.actions || {};   // los módulos pueden registrar funciones aquí

    function resolve(name) {
        if (Object.prototype.hasOwnProperty.call(Bulwark.actions, name)) return Bulwark.actions[name];
        var fn = window[name];
        return (typeof fn === "function") ? fn : null;
    }

    function parseArgs(el) {
        var raw = el.getAttribute("data-args");
        if (!raw) return [];
        try { var v = JSON.parse(raw); return Array.isArray(v) ? v : [v]; }
        catch (e) { return [raw]; }
    }

    // --- click: data-href / data-confirm / data-call ---
    document.addEventListener("click", function (ev) {
        var el = ev.target.closest("[data-href],[data-confirm],[data-call]");
        if (!el) return;

        if (el.hasAttribute("data-confirm")) {
            if (!window.confirm(el.getAttribute("data-confirm"))) { ev.preventDefault(); return; }
        }
        if (el.hasAttribute("data-call")) {
            var fn = resolve(el.getAttribute("data-call"));
            if (fn) { ev.preventDefault(); fn.apply(el, parseArgs(el)); return; }
        }
        if (el.hasAttribute("data-href")) {
            ev.preventDefault();
            window.location.href = el.getAttribute("data-href");
        }
    });

    // --- submit: data-confirm en <form> ---
    document.addEventListener("submit", function (ev) {
        var form = ev.target;
        if (form && form.hasAttribute && form.hasAttribute("data-confirm")) {
            if (!window.confirm(form.getAttribute("data-confirm"))) { ev.preventDefault(); }
        }
    });
})();

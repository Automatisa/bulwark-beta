/*
 * csp-shim.js — Delegación de eventos para eliminar los manejadores inline (onclick/onsubmit…)
 * del tema legacy y poder retirar 'unsafe-inline' de script-src (CSP, Fase 2 — ver csp_panel.md).
 *
 * Sustituye los patrones inline por atributos data-* + listeners delegados (un solo listener en
 * document, funciona con contenido añadido dinámicamente):
 *   onclick="window.location.href='X';return false;"  ->  data-href="X"
 *   onclick="return confirm('msg')"                    ->  data-confirm="msg"  (en <a>/<button>)
 *   onsubmit="return confirm('msg')"                   ->  data-confirm="msg"  (en <form>)
 *   onkeypress="return isNumberKey(event)"             ->  data-numeric="int"  (solo dígitos)
 *   onkeypress="return isNumberKeyOrNeg(event)"        ->  data-numeric="neg"  (dígitos y '-')
 *   onclick="fn('a','b')"                              ->  data-call="fn" data-args='["a","b"]'
 *
 * data-call resuelve la función por nombre en Bulwark.actions (registro) o en window (funciones
 * globales del módulo). Sin eval: los args se leen de un JSON en data-args. Si data-pass-event
 * está presente, el evento se pasa como PRIMER argumento (para handlers tipo openTABS(evt, ...)).
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

    function byId(id) { return id ? document.getElementById(id) : null; }

    // --- click: data-show / data-hide / data-toggle-vis / data-href / data-confirm / data-call ---
    document.addEventListener("click", function (ev) {
        var el = ev.target.closest(
            "[data-href],[data-confirm],[data-call],[data-show],[data-hide],[data-toggle-vis]," +
            "[data-password-toggle],[data-password-show],[data-check]");
        if (!el) return;

        // Mostrar/ocultar/alternar visibilidad (sustituye show_div/hide_div/toggle_visibility).
        // NO hacemos preventDefault salvo en <a>, porque muchos disparadores son radios/checkboxes
        // y bloquear su acción por defecto impediría seleccionarlos.
        if (el.hasAttribute("data-show") || el.hasAttribute("data-hide") ||
            el.hasAttribute("data-toggle-vis") || el.hasAttribute("data-password-toggle") ||
            el.hasAttribute("data-password-show") || el.hasAttribute("data-check")) {
            var sEl = byId(el.getAttribute("data-show"));   if (sEl) sEl.style.display = "block";
            var hEl = byId(el.getAttribute("data-hide"));   if (hEl) hEl.style.display = "none";
            var tEl = byId(el.getAttribute("data-toggle-vis"));
            if (tEl) tEl.style.display = (tEl.style.display === "none" ? "block" : "none");
            var pEl = byId(el.getAttribute("data-password-toggle"));
            if (pEl) pEl.type = (pEl.type === "password" ? "text" : "password");
            var psEl = byId(el.getAttribute("data-password-show"));
            if (psEl) psEl.type = "text";                         // revelar (no alternar)
            var ckEl = byId(el.getAttribute("data-check"));
            if (ckEl) ckEl.checked = true;                        // marcar checkbox
            if (el.tagName === "A") ev.preventDefault();
            // sin return: no interfiere con otras acciones data-* del mismo elemento
        }

        if (el.hasAttribute("data-confirm")) {
            if (!window.confirm(el.getAttribute("data-confirm"))) { ev.preventDefault(); return; }
        }
        if (el.hasAttribute("data-call")) {
            var fn = resolve(el.getAttribute("data-call"));
            if (fn) {
                ev.preventDefault();
                var args = parseArgs(el);
                if (el.hasAttribute("data-pass-event")) { args.unshift(ev); }
                fn.apply(el, args);
                return;
            }
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

    // --- keypress: filtro numérico (data-numeric="int" | "neg") ---
    // Reemplaza los onkeypress="return isNumberKey(event)" / isNumberKeyOrNeg(event) del tema.
    // Deja pasar teclas de control (charCode <= 31: Enter, Backspace, flechas…), dígitos 0-9,
    // y en modo "neg" también el signo menos (para valores tipo -1 = ilimitado).
    document.addEventListener("keypress", function (ev) {
        var el = ev.target.closest("[data-numeric]");
        if (!el) return;
        var cc = ev.which || ev.keyCode;
        if (cc <= 31) return;                       // control: permitir
        var ok = (cc >= 48 && cc <= 57);            // dígitos
        if (!ok && el.getAttribute("data-numeric") === "neg" && (cc === 45 || cc === 109)) {
            ok = true;                              // '-' (teclado principal o numpad)
        }
        if (!ok) { ev.preventDefault(); }
    });
})();

/*
 * login.js — Lógica de la pantalla de login, externalizada del <script> inline de login.ztml
 * para poder retirar 'unsafe-inline' de script-src (CSP Fase 2 — ver csp_panel.md).
 *
 * Los bindings de #frmZConfirm son inertes si el formulario no existe (selector vacío),
 * por lo que no hace falta el condicional PHP que había en la plantilla.
 */
$(function () {
    // Alternar login <-> "olvidé contraseña".
    $('#forgotpw').on('click', function (e) {
        e.preventDefault();
        $('#frmZLogin').slideUp('fast', function () { $('#frmZForgot').slideDown('fast'); });
    });
    $('#backtologin').on('click', function (e) {
        e.preventDefault();
        $('#frmZForgot').slideUp('fast', function () { $('#frmZLogin').slideDown('fast'); });
    });

    // Validación del formulario de restablecimiento (solo presente en ?resetkey=...).
    $('#frmZConfirm').on('submit', function () {
        if ($('#inputNewPass1').val() !== $('#inputNewPass2').val() || !$('#inputNewPass1').val()) {
            $('#msgMatchErr').show();
            return false;
        }
        $('#msgMatchErr').hide();
        return true;
    });
});

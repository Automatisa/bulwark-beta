/*
 * password-tools.js — Generador de contraseñas + medidor de fuerza, unificados de los <script>
 * inline duplicados en mysql_users/ftp_management/manage_clients/mailboxes (CSP Fase 2).
 * Cargado globalmente en master.ztml; es no-op en páginas sin campo de contraseña. La longitud
 * mínima se lee del atributo pattern del campo (.{N,}), en vez de interpolar <@ MinPassLength @>.
 */
(function () {
    function minLen(inp) {
        var m = inp && inp.getAttribute('pattern') && inp.getAttribute('pattern').match(/\.\{(\d+),/);
        return m ? parseInt(m[1], 10) : 8;
    }

    function genPassword(len) {
        var chars = "ABCDEFGHIJKLMNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz";
        var out = "", charCount = 0, numCount = 0;
        for (var i = 0; i < len; i++) {
            if ((Math.floor(Math.random() * 2) === 0 && numCount < 5) || charCount >= 15) {
                out += Math.floor(Math.random() * 5); numCount++;
            } else {
                var r = Math.floor(Math.random() * chars.length);
                out += chars.substring(r, r + 1); charCount++;
            }
        }
        return out;
    }

    function attachMeter(inp) {
        var letter = document.getElementById('letter'), capital = document.getElementById('capital'),
            number = document.getElementById('number'), length = document.getElementById('length'),
            message = document.getElementById('message');
        var min = minLen(inp);
        function set(el, ok) { if (!el) return; el.classList.remove(ok ? 'invalid' : 'valid'); el.classList.add(ok ? 'valid' : 'invalid'); }
        function validate() {
            var v = inp.value;
            set(letter, /[a-z]/.test(v));
            set(capital, /[A-Z]/.test(v));
            set(number, /[0-9]/.test(v));
            set(length, v.length >= min);
        }
        inp.addEventListener('focus', function () { if (message) message.style.display = 'block'; });
        inp.addEventListener('blur', function () { if (message) message.style.display = 'none'; });
        inp.addEventListener('click', validate);
        inp.addEventListener('keyup', validate);
    }

    function init() {
        // Generar: los enlaces .link-password con id=generate rellenan los campos .inPassword.
        document.querySelectorAll('.link-password').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                if (link.id === 'generate') {
                    var fields = document.querySelectorAll('.inPassword');
                    var pw = genPassword(minLen(fields[0]));
                    fields.forEach(function (f) { f.value = pw; });
                }
            });
        });
        // Medidor de fuerza (solo si existe el campo y el panel de requisitos).
        var inp = document.getElementById('inPassword');
        if (inp && document.getElementById('message')) attachMeter(inp);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();

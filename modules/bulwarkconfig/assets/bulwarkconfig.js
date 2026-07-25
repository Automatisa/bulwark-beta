/* bulwarkconfig.js — externalizado de los <script> inline (CSP Fase 2). Puerto por isla #bwcfg-data. */
(function(){var _p=document.getElementById('bwcfg-data');window.__BW_PORT=_p?(JSON.parse(_p.textContent||'{}').port||'80'):'80';})();
(function() {
                var port = parseInt(window.__BW_PORT,10) || 80;

                function buildNote() {
                    var previewUrl = document.getElementById('panelUrlPreview').textContent;
                    var html = '';

                    if (port === 80) {
                        html = '<div class="alert alert-info">'
                             + '<span class="bi bi-info-circle"></span> '
                             + 'Puerto estándar HTTP. El panel será accesible directamente en '
                             + '<strong>' + previewUrl + '</strong> sin necesidad de escribir el puerto.<br>'
                             + 'Si en el futuro activas SSL en el panel, el puerto cambiará a 443 y '
                             + 'el acceso HTTP redirigirá automáticamente a HTTPS.'
                             + '<br><br>Asegúrate de que existe un <strong>registro A</strong> en el DNS Manager '
                             + 'para el subdominio que elijas, apuntando a la IP del servidor.'
                             + '</div>';
                    } else if (port === 443) {
                        html = '<div class="alert alert-success">'
                             + '<span class="bi bi-lock"></span> '
                             + 'Puerto estándar HTTPS. El panel será accesible en '
                             + '<strong>' + previewUrl + '</strong>.<br>'
                             + 'Las visitas al puerto 80 (HTTP) se redirigen automáticamente a HTTPS, '
                             + 'por lo que no es necesario escribir el puerto en la URL.'
                             + '<br><br>Asegúrate de que existe un <strong>registro A</strong> en el DNS Manager '
                             + 'para el subdominio que elijas, apuntando a la IP del servidor.'
                             + '</div>';
                    } else {
                        html = '<div class="alert alert-warning">'
                             + '<span class="bi bi-exclamation-triangle"></span> '
                             + 'Puerto no estándar <strong>' + port + '</strong>. '
                             + 'Los usuarios tendrán que escribir siempre el puerto en la URL: '
                             + '<strong>' + previewUrl + '</strong><br>'
                             + 'El navegador sin puerto va al 80, donde el panel <em>no</em> estará.<br>'
                             + 'Para producción se recomienda usar el puerto <strong>80</strong> (HTTP) '
                             + 'o el <strong>443</strong> (HTTPS con SSL activo). '
                             + 'El puerto se cambia en el formulario de settings de arriba.'
                             + '<br><br>Si cambias a un puerto no estándar, ese puerto debe estar '
                             + '<strong>abierto en el firewall</strong> del servidor.'
                             + '</div>';
                    }
                    document.getElementById('panelDomainNote').innerHTML = html;
                }

                document.getElementById('inPanelPrefix').addEventListener('input', buildNote);
                document.getElementById('inRootDomain').addEventListener('change', buildNote);
                setTimeout(buildNote, 60);
            })();
(function() {
            var reserved = ["ns1","ns2","ns3","ns4","mail","smtp","pop","pop3","imap",
                            "ftp","www","webmail","autodiscover","autoconfig","vpn","ssh",
                            "mx","mx1","mx2","api"];
            var port = window.__BW_PORT;

            function update() {
                var prefix = document.getElementById('inPanelPrefix').value.trim().toLowerCase();
                var root   = document.getElementById('inRootDomain').value;
                var warn   = document.getElementById('panelDomainWarning');
                var btn    = document.getElementById('btnSavePanelDomain');
                var isReserved = prefix !== '' && reserved.indexOf(prefix) !== -1;

                warn.style.display = isReserved ? 'block' : 'none';
                btn.disabled = isReserved;

                var fqdn = prefix !== '' ? prefix + '.' + root : root;
                var portSuffix = (port === '80' || port === '443') ? '' : ':' + port;
                document.getElementById('panelUrlPreview').textContent =
                    'http://' + fqdn + portSuffix + '/';
            }

            document.getElementById('inPanelPrefix').addEventListener('input', update);
            document.getElementById('inRootDomain').addEventListener('change', update);

            document.getElementById('formPanelDomain').addEventListener('submit', function(e) {
                var fqdn = document.getElementById('panelUrlPreview').textContent;
                if (!confirm('¿Cambiar el dominio del panel a:\n' + fqdn + '?')) e.preventDefault();
            });

            update();
        })();

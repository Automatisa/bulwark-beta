/* client_notices.js — init de SCEditor externalizado (CSP Fase 2). La ruta del tema se
 * lee del atributo data-sceditor-style del textarea, en vez de interpolarla en el JS. */
(function () {
    function init() {
        var textarea = document.getElementById('inNotice');
        if (!textarea || typeof sceditor === 'undefined') return;
        sceditor.create(textarea, {
            format: 'bbcode',
            icons: 'monocons',
            toolbar: 'bold,italic,font,size,underline,color,removeformat|cut,copy,paste|bulletlist,orderedlist|email,link,unlink|date,time|source',
            style: textarea.getAttribute('data-sceditor-style') || ''
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();

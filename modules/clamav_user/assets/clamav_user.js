/* clamav_user.js — paginación de cuarentena (CSP Fase 2). */
(function () {
    var tbody = document.getElementById('clamav-qtbody');
    var pager = document.getElementById('clamav-qpager');
    var sel   = document.getElementById('clamav-qperpage');
    if (tbody && pager) {
        var rows = Array.prototype.slice.call(tbody.rows);
        var total = rows.length, perPage = 25, current = 1;

        function pageCount() { return Math.max(1, Math.ceil(total / perPage)); }

        function showPage() {
            var pages = pageCount();
            if (current > pages) current = pages;
            var start = (current - 1) * perPage, end = start + perPage;
            for (var i = 0; i < total; i++) rows[i].style.display = (i >= start && i < end) ? '' : 'none';
            buildPager();
        }

        function pageButton(label, page, opts) {
            opts = opts || {};
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'btn btn-sm ' + (opts.active ? 'btn-secondary' : 'btn-outline-secondary');
            b.innerHTML = label;
            if (opts.disabled) b.disabled = true;
            else b.addEventListener('click', function () {
                current = page; showPage();
                var box = tbody.closest('.table-responsive');
                if (box) window.scrollTo({ top: box.getBoundingClientRect().top + window.pageYOffset - 70, behavior: 'smooth' });
            });
            return b;
        }

        function dots() {
            var s = document.createElement('span');
            s.className = 'text-muted'; s.style.padding = '0 4px'; s.textContent = '…';
            return s;
        }

        function buildPager() {
            pager.innerHTML = '';
            var pages = pageCount();
            if (pages <= 1) {
                var info = document.createElement('span');
                info.className = 'text-muted'; info.style.fontSize = '12px';
                info.textContent = total + ' archivo' + (total === 1 ? '' : 's');
                pager.appendChild(info);
                return;
            }
            pager.appendChild(pageButton('<i class="bi bi-chevron-left"></i>', current - 1, { disabled: current === 1 }));
            var from = Math.max(1, current - 2), to = Math.min(pages, from + 4);
            from = Math.max(1, to - 4);
            if (from > 1) { pager.appendChild(pageButton('1', 1, {})); if (from > 2) pager.appendChild(dots()); }
            for (var p = from; p <= to; p++) pager.appendChild(pageButton(String(p), p, { active: p === current }));
            if (to < pages) { if (to < pages - 1) pager.appendChild(dots()); pager.appendChild(pageButton(String(pages), pages, {})); }
            pager.appendChild(pageButton('<i class="bi bi-chevron-right"></i>', current + 1, { disabled: current === pages }));
        }

        if (sel) sel.addEventListener('change', function () {
            perPage = parseInt(this.value, 10) || 25; current = 1; showPage();
        });
        showPage();
    }

    var count = parseInt('<@ QuarantineCount @>', 10) || 0;
    if (count > 0) {
        document.querySelectorAll('a[href*="module=clamav_user"]').forEach(function (link) {
            if (!link.querySelector('.av-badge')) {
                var badge = document.createElement('span');
                badge.className = 'av-badge';
                badge.style.cssText = 'background:#d9534f;color:#fff;border-radius:9px;padding:1px 6px;font-size:11px;margin-left:5px;vertical-align:middle;display:inline-block;';
                badge.textContent = count;
                link.appendChild(badge);
            }
        });
    }
}());

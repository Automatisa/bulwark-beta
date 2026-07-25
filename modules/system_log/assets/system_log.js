/* system_log.js — externalizado del <script> inline (CSP Fase 2). */
(function () {
	var tbody = document.getElementById('syslog-tbody');
	var pager = document.getElementById('syslog-pager');
	var sel   = document.getElementById('syslog-perpage');
	if (!tbody || !pager) return;

	var rows    = Array.prototype.slice.call(tbody.rows);
	var total   = rows.length;
	var perPage = 25;
	var current = 1;

	function pageCount() { return Math.max(1, Math.ceil(total / perPage)); }

	function showPage() {
		var pages = pageCount();
		if (current > pages) current = pages;
		var start = (current - 1) * perPage;
		var end   = start + perPage;
		for (var i = 0; i < total; i++) {
			rows[i].style.display = (i >= start && i < end) ? '' : 'none';
		}
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
			current = page;
			showPage();
			var box = tbody.closest('.table-responsive');
			if (box) window.scrollTo({ top: box.getBoundingClientRect().top + window.pageYOffset - 70, behavior: 'smooth' });
		});
		return b;
	}

	function buildPager() {
		pager.innerHTML = '';
		var pages = pageCount();
		if (pages <= 1) {
			var info = document.createElement('span');
			info.className = 'text-muted';
			info.style.fontSize = '12px';
			info.textContent = total + ' registros';
			pager.appendChild(info);
			return;
		}
		pager.appendChild(pageButton('<i class="bi bi-chevron-left"></i>', current - 1, { disabled: current === 1 }));
		// ventana de páginas alrededor de la actual
		var from = Math.max(1, current - 2);
		var to   = Math.min(pages, from + 4);
		from = Math.max(1, to - 4);
		if (from > 1) { pager.appendChild(pageButton('1', 1, {})); if (from > 2) pager.appendChild(dots()); }
		for (var p = from; p <= to; p++) pager.appendChild(pageButton(String(p), p, { active: p === current }));
		if (to < pages) { if (to < pages - 1) pager.appendChild(dots()); pager.appendChild(pageButton(String(pages), pages, {})); }
		pager.appendChild(pageButton('<i class="bi bi-chevron-right"></i>', current + 1, { disabled: current === pages }));
	}

	function dots() {
		var s = document.createElement('span');
		s.className = 'text-muted';
		s.style.padding = '0 4px';
		s.textContent = '…';
		return s;
	}

	if (sel) sel.addEventListener('change', function () {
		perPage = parseInt(this.value, 10) || 25;
		current = 1;
		showPage();
	});

	showPage();
})();

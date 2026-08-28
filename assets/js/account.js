/**
 * Panel: kopyala, kupon formu (indirim ↔ komisyon, kapsam, canlı örnek), link üreteci (ürün arama),
 * içerik kiti kupon seçici, silme onayı, grafik tooltip.
 */
(function () {
	'use strict';
	var cfg  = window.ssa_account || {};
	var i18n = cfg.i18n || { copied: 'Copied!', copy: 'Copy' };
	var $    = window.jQuery;

	function money(n) {
		var d = typeof cfg.decimals === 'number' ? cfg.decimals : 2;
		var s = Number(n).toFixed(d);
		var parts = s.split('.');
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, cfg.th_sep || '.');
		var num = parts.join(cfg.dec_sep || ',');
		var c = cfg.currency || '';
		switch (cfg.pos) {
			case 'left': return c + num;
			case 'left_space': return c + ' ' + num;
			case 'right_space': return num + ' ' + c;
			default: return num + c;
		}
	}

	/* Kopyala */
	function copyText(text, btn) {
		var done = function () { var old = btn.textContent; btn.textContent = i18n.copied; btn.classList.add('is-copied'); setTimeout(function () { btn.textContent = old; btn.classList.remove('is-copied'); }, 1500); };
		if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(text).then(done); }
		else { var ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); } catch (e) {} document.body.removeChild(ta); done(); }
	}
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.ssa-copy');
		if (!btn) { return; }
		e.preventDefault();
		var text = btn.dataset.copy;
		if (!text && btn.dataset.copyTarget) { var el = document.querySelector(btn.dataset.copyTarget); text = el ? (el.value || el.textContent) : ''; }
		if (text) { copyText(text.trim(), btn); }
	});

	/* Silme onayı */
	document.addEventListener('submit', function (e) {
		var f = e.target.closest('form.ssa-confirm');
		if (f && !window.confirm(f.dataset.confirm || '?')) { e.preventDefault(); }
	});

	/* Ürün arama (selectWoo, çoklu) */
	function productSearch(el, multiple) {
		if (!el || !$ || !$.fn.selectWoo) { return null; }
		var $el = $(el);
		$el.selectWoo({
			width: '100%',
			multiple: !!multiple,
			allowClear: !multiple,
			minimumInputLength: 2,
			placeholder: el.dataset.placeholder || '',
			language: { inputTooShort: function () { return i18n.search || ''; }, noResults: function () { return i18n.no_results || ''; } },
			ajax: {
				url: cfg.search,
				dataType: 'json',
				delay: 250,
				data: function (params) { return { term: params.term, security: cfg.nonce }; },
				processResults: function (data) { return { results: data }; }
			}
		});
		return $el;
	}

	/* Kupon formu */
	var form = document.querySelector('.ssa-coupon-form');
	if (form) {
		var share = parseFloat(form.dataset.share || '15');
		var linkPct = parseFloat(form.dataset.link || '10');
		var example = parseFloat(form.dataset.example || '1000');
		var range = document.getElementById('ssa-c-range');
		var num = document.getElementById('ssa-c-num');
		var code = document.getElementById('ssa-c-code');
		var barC = document.getElementById('ssa-c-bar-c'), barD = document.getElementById('ssa-c-bar-d');

		var render = function (d) {
			d = Math.min(parseFloat(range.max), Math.max(parseFloat(range.min), isNaN(d) ? parseFloat(range.min) : d));
			var c = Math.max(0, share - d);
			document.getElementById('ssa-c-commission').textContent = c.toFixed(1);
			document.getElementById('ssa-c-discount').textContent = d.toFixed(1);
			if (barC) { barC.style.width = (c / share * 100) + '%'; }
			if (barD) { barD.style.width = (d / share * 100) + '%'; }
			var pays = example * (1 - d / 100);
			document.getElementById('ssa-ex-pays').textContent = money(pays);
			document.getElementById('ssa-ex-earn').textContent = money(pays * c / 100);
			document.getElementById('ssa-ex-link').textContent = money(example * linkPct / 100);
		};
		range.addEventListener('input', function () { num.value = range.value; render(parseFloat(range.value)); });
		num.addEventListener('input', function () { range.value = num.value; render(parseFloat(num.value)); });
		render(parseFloat(num.value));

		if (code) {
			code.addEventListener('input', function () {
				var pos = code.selectionStart;
				code.value = code.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
				try { code.setSelectionRange(pos, pos); } catch (e) {}
			});
		}

		var panels = form.querySelectorAll('.ssa-scope-panel');
		var syncScope = function () {
			var v = (form.querySelector('input[name="scope_type"]:checked') || {}).value || 'all';
			panels.forEach(function (p) { p.classList.toggle('is-open', p.dataset.scope === v); });
		};
		form.querySelectorAll('input[name="scope_type"]').forEach(function (r) { r.addEventListener('change', syncScope); });
		syncScope();

		productSearch(document.getElementById('ssa-c-products'), true);
		var cats = document.getElementById('ssa-c-categories');
		if (cats && $ && $.fn.selectWoo) { $(cats).selectWoo({ width: '100%', multiple: true, placeholder: cats.dataset.placeholder || '' }); }
	}

	/* Link üreteci */
	var gen = document.querySelector('.ssa-link-generator');
	if (gen) {
		var out = document.getElementById('ssa-link-output');
		var url = document.getElementById('ssa-link-url');
		var cat = document.getElementById('ssa-link-category');
		var prod = document.getElementById('ssa-link-product');
		var base = '';
		var build = function () {
			var b = (base || (url && url.value) || (cat && cat.value) || cfg.home || '/').trim();
			try { var u = new URL(b, window.location.origin); u.searchParams.set('ref', gen.dataset.code); out.textContent = u.toString(); } catch (e) { out.textContent = b; }
		};
		if (url) { url.addEventListener('input', function () { base = ''; build(); }); }
		if (cat) { cat.addEventListener('change', function () { base = ''; if (url) { url.value = ''; } build(); }); }
		var $prod = productSearch(prod, false);
		if ($prod) {
			$prod.on('select2:select', function (e) { base = e.params.data.url || ''; if (url) { url.value = ''; } build(); });
			$prod.on('select2:clear', function () { base = ''; build(); });
		}
	}

	/* İçerik kiti: kupon seçici */
	var pick = document.getElementById('ssa-kit-coupon');
	if (pick) {
		var fill = function () {
			var o = pick.options[pick.selectedIndex];
			if (!o) { return; }
			document.querySelectorAll('.ssa-kit-text[data-template]').forEach(function (el) {
				el.textContent = el.dataset.template.split('{code}').join(o.dataset.code).split('{discount}').join(o.dataset.discount);
			});
		};
		pick.addEventListener('change', fill);
		fill();
	}

	/* Grafik tooltip'i */
	var tip = null;
	function showTip(el, e) {
		if (!tip) { tip = document.createElement('div'); tip.className = 'ssa-tooltip'; document.body.appendChild(tip); }
		tip.innerHTML = '<b>' + (el.dataset.label || '') + (el.dataset.series ? ' · ' + el.dataset.series : '') + '</b>' + (el.dataset.value || '');
		tip.style.left = (e.clientX + 12) + 'px'; tip.style.top = (e.clientY + 12) + 'px';
		tip.classList.add('is-visible');
	}
	document.addEventListener('mousemove', function (e) { var el = e.target.closest && e.target.closest('.ssa-bar, .ssa-donut-seg'); if (el) { showTip(el, e); } else if (tip) { tip.classList.remove('is-visible'); } });
})();

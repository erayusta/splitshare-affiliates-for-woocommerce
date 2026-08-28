/**
 * Panel: kopyala, dağılım editörü (canlı çubuk + örnek hesap), link üreteci (ürün arama), grafik tooltip.
 */
(function () {
	'use strict';
	var cfg  = window.ssa_account || {};
	var i18n = cfg.i18n || { copied: 'Copied!', copy: 'Copy' };

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

	// Dağılım editörü
	var editor = document.querySelector('.ssa-split-editor');
	if (editor) {
		var range = document.getElementById('ssa-split-range');
		var share = parseFloat(editor.dataset.share || '15');
		var example = parseFloat(editor.dataset.example || '1000');
		var barC = editor.querySelector('.ssa-split__c'), barD = editor.querySelector('.ssa-split__d');
		var update = function () {
			var c = parseFloat(range.value), d = share - c;
			document.getElementById('ssa-split-commission').textContent = c.toFixed(1);
			document.getElementById('ssa-split-discount').textContent = d.toFixed(1);
			if (barC) { barC.style.width = (c / share * 100) + '%'; }
			if (barD) { barD.style.width = (d / share * 100) + '%'; }
			var pays = example * (1 - d / 100);
			document.getElementById('ssa-ex-pays').textContent = money(pays);
			document.getElementById('ssa-ex-earn').textContent = money(pays * c / 100);
			document.getElementById('ssa-ex-link').textContent = money(example * c / 100);
			editor.querySelectorAll('.ssa-preset').forEach(function (b) { b.classList.toggle('is-active', parseFloat(b.dataset.value) === c); });
		};
		range.addEventListener('input', update);
		editor.querySelectorAll('.ssa-preset').forEach(function (b) { b.addEventListener('click', function () { range.value = b.dataset.value; update(); }); });
		update();
	}

	// Link üreteci
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
		if (prod && window.jQuery && window.jQuery.fn.selectWoo) {
			var $prod = window.jQuery(prod);
			$prod.selectWoo({
				width: '100%',
				allowClear: true,
				minimumInputLength: 2,
				placeholder: prod.dataset.placeholder || '',
				ajax: {
					url: cfg.search,
					dataType: 'json',
					delay: 250,
					data: function (params) { return { term: params.term, security: cfg.nonce }; },
					processResults: function (data) { return { results: data }; }
				}
			});
			$prod.on('select2:select', function (e) { base = e.params.data.url || ''; if (url) { url.value = ''; } build(); });
			$prod.on('select2:clear', function () { base = ''; build(); });
		}
	}

	// Grafik tooltip'i
	var tip = null;
	function showTip(el, e) {
		if (!tip) { tip = document.createElement('div'); tip.className = 'ssa-tooltip'; document.body.appendChild(tip); }
		tip.innerHTML = '<b>' + (el.dataset.label || '') + (el.dataset.series ? ' · ' + el.dataset.series : '') + '</b>' + (el.dataset.value || '');
		tip.style.left = (e.clientX + 12) + 'px'; tip.style.top = (e.clientY + 12) + 'px';
		tip.classList.add('is-visible');
	}
	document.addEventListener('mousemove', function (e) { var el = e.target.closest && e.target.closest('.ssa-bar, .ssa-donut-seg'); if (el) { showTip(el, e); } else if (tip) { tip.classList.remove('is-visible'); } });
})();

/**
 * Panel: kopyala, pay kaydırıcısı, link üreteci.
 */
(function () {
	'use strict';
	var i18n = (window.ssa_account && window.ssa_account.i18n) || { copied: 'Copied!', copy: 'Copy' };

	function copyText(text, btn) {
		var done = function () {
			var old = btn.textContent;
			btn.textContent = i18n.copied;
			setTimeout(function () { btn.textContent = old; }, 1500);
		};
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(done);
		} else {
			var ta = document.createElement('textarea');
			ta.value = text; document.body.appendChild(ta); ta.select();
			try { document.execCommand('copy'); } catch (e) {}
			document.body.removeChild(ta); done();
		}
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.ssa-copy');
		if (!btn) { return; }
		e.preventDefault();
		var text = btn.dataset.copy;
		if (!text && btn.dataset.copyTarget) {
			var el = document.querySelector(btn.dataset.copyTarget);
			text = el ? (el.value || el.textContent) : '';
		}
		if (text) { copyText(text.trim(), btn); }
	});

	var range = document.getElementById('ssa-split-range');
	if (range) {
		var share = parseFloat(range.dataset.share || '15');
		var update = function () {
			var c = parseFloat(range.value);
			document.getElementById('ssa-split-commission').textContent = c.toFixed(1);
			document.getElementById('ssa-split-discount').textContent = (share - c).toFixed(1);
		};
		range.addEventListener('input', update);
		document.querySelectorAll('.ssa-preset').forEach(function (b) {
			b.addEventListener('click', function () { range.value = b.dataset.value; update(); });
		});
	}

	var gen = document.querySelector('.ssa-link-generator');
	if (gen) {
		var out = document.getElementById('ssa-link-output');
		var url = document.getElementById('ssa-link-url');
		var cat = document.getElementById('ssa-link-category');
		var build = function () {
			var base = (url.value || cat.value || (window.ssa_account && window.ssa_account.home) || '/').trim();
			try {
				var u = new URL(base, window.location.origin);
				u.searchParams.set('ref', gen.dataset.code);
				out.textContent = u.toString();
			} catch (e) { out.textContent = base; }
		};
		url.addEventListener('input', build);
		cat.addEventListener('change', function () { url.value = ''; build(); });
	}
})();

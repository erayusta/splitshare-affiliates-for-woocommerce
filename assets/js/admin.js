/**
 * Admin: tekrarlı satır tabloları (ayarlar), medya seçici, grafik tooltip'i, kopyala, alt sekmeler.
 */
(function ($) {
	'use strict';

	$(document).on('click', '.ssa-row-add', function (e) {
		e.preventDefault();
		var $td = $(this).closest('td');
		$td.find('table.ssa-rows tbody').append($td.find('.ssa-row-template').html().replace(/__i__/g, Date.now()));
	});
	$(document).on('click', '.ssa-row-remove', function (e) { e.preventDefault(); $(this).closest('tr').remove(); });

	$(document).on('click', '.ssa-media-pick', function (e) {
		e.preventDefault();
		var $input = $(this).siblings('.ssa-media-ids');
		var frame = wp.media({ title: 'Content kit', multiple: true });
		frame.on('select', function () {
			var ids = frame.state().get('selection').map(function (a) { return String(a.id); });
			var existing = $input.val() ? $input.val().split(',') : [];
			$input.val(existing.concat(ids).filter(function (v, i, a) { return v && a.indexOf(v) === i; }).join(','));
		});
		frame.open();
	});

	$(document).on('click', '.ssa-confirm', function () { return window.confirm($(this).data('confirm') || 'Are you sure?'); });

	// Kopyala
	$(document).on('click', '.ssa-copy', function (e) {
		e.preventDefault();
		var btn = this, text = btn.dataset.copy;
		if (!text && btn.dataset.copyTarget) { var el = document.querySelector(btn.dataset.copyTarget); text = el ? (el.value || el.textContent) : ''; }
		if (!text) { return; }
		var done = function () { btn.classList.add('is-copied'); setTimeout(function () { btn.classList.remove('is-copied'); }, 1200); };
		if (navigator.clipboard) { navigator.clipboard.writeText(text.trim()).then(done); } else { done(); }
	});

	// Grafik tooltip'i (bar/donut segmentleri)
	var tip = null;
	function showTip(el, e) {
		if (!tip) { tip = document.createElement('div'); tip.className = 'ssa-tooltip'; document.body.appendChild(tip); }
		tip.innerHTML = '<b>' + (el.dataset.label || '') + (el.dataset.series ? ' · ' + el.dataset.series : '') + '</b>' + (el.dataset.value || '');
		tip.style.left = (e.clientX + 12) + 'px';
		tip.style.top = (e.clientY + 12) + 'px';
		tip.classList.add('is-visible');
	}
	$(document).on('mousemove', '.ssa-bar, .ssa-donut-seg', function (e) { showTip(this, e); });
	$(document).on('mouseleave', '.ssa-bar, .ssa-donut-seg', function () { if (tip) { tip.classList.remove('is-visible'); } });

	// Alt sekmeler (ortak profili)
	$(document).on('click', '.ssa-subtab', function () {
		var target = this.dataset.target;
		var $wrap = $(this).closest('.ssa-subtabs-wrap');
		$wrap.find('.ssa-subtab').removeClass('is-active'); $(this).addClass('is-active');
		$wrap.find('.ssa-subpanel').removeClass('is-active'); $wrap.find(target).addClass('is-active');
		if (window.history.replaceState) { window.history.replaceState(null, '', target); }
	});
	if (window.location.hash && $('.ssa-subtab[data-target="' + window.location.hash + '"]').length) {
		$('.ssa-subtab[data-target="' + window.location.hash + '"]').trigger('click');
	}
})(jQuery);

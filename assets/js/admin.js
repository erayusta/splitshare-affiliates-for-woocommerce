/**
 * Admin: tekrarlı satır tabloları (ayarlar) ve medya seçici.
 */
(function ($) {
	'use strict';

	$(document).on('click', '.ssa-row-add', function (e) {
		e.preventDefault();
		var $table = $(this).closest('td').find('table.ssa-rows');
		var tpl = $(this).closest('td').find('.ssa-row-template').html();
		var i = Date.now();
		$table.find('tbody').append(tpl.replace(/__i__/g, i));
	});

	$(document).on('click', '.ssa-row-remove', function (e) {
		e.preventDefault();
		$(this).closest('tr').remove();
	});

	$(document).on('click', '.ssa-media-pick', function (e) {
		e.preventDefault();
		var $input = $(this).siblings('.ssa-media-ids');
		var frame = wp.media({ title: 'Content kit', multiple: true });
		frame.on('select', function () {
			var ids = frame.state().get('selection').map(function (a) { return a.id; });
			var existing = $input.val() ? $input.val().split(',') : [];
			$input.val(existing.concat(ids).filter(function (v, i, a) { return v && a.indexOf(v) === i; }).join(','));
		});
		frame.open();
	});

	$(document).on('click', '.ssa-confirm', function () {
		return window.confirm($(this).data('confirm') || 'Are you sure?');
	});
})(jQuery);

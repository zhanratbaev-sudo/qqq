/* StudyHub — settings helpers: media picker + repeatable rows */
(function ($) {
  'use strict';
  $(document).on('click', '.sh-pick', function (e) {
    e.preventDefault();
    var input = $(this).siblings('input');
    var frame = wp.media({ title: 'Выберите изображение', library: { type: 'image' }, multiple: false });
    frame.on('select', function () {
      input.val(frame.state().get('selection').first().toJSON().url).trigger('change');
    });
    frame.open();
  });

  $(document).on('click', '.sh-remove', function () {
    if (confirm('Удалить строку?')) $(this).closest('.sh-row').remove();
  });

  $(document).on('click', '.sh-add', function () {
    var name = $(this).data('target');
    var box = $('[data-repeat="' + name + '"]');
    var tpl = document.getElementById('sh-tpl-' + name).innerHTML;
    var idx = Date.now();
    box.append(tpl.replace(/__i__/g, idx));
  });
})(jQuery);

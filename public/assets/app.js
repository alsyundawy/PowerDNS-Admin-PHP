$(function () {
  $('#add-row').on('click', function () {
    const node = document.getElementById('row-template').content.cloneNode(true);
    $('#record-table tbody').append(node);
  });
  $('#record-table').on('click', '.rm-row', function () {
    $(this).closest('tr').remove();
  });
  $('#record-filter').on('input', function () {
    const q = $(this).val().toLowerCase();
    $('#record-table tbody tr').each(function () {
      const text = $(this).text().toLowerCase() + ' ' + $(this).find('input').map(function () { return this.value; }).get().join(' ').toLowerCase();
      $(this).toggle(text.indexOf(q) !== -1);
    });
  });
  $('#record-form').on('submit', function () {
    $('#record-table tbody tr').each(function (i) {
      const box = $(this).find('input[type=checkbox]');
      box.attr('name', 'r_disabled[' + i + ']');
      if (!box.prop('checked')) {
        box.after('<input type="hidden" name="r_disabled[' + i + ']" value="0">');
      }
    });
  });
});

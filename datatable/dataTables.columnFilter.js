/**
 * DataTables Column Filter Plugin
 * Adds input boxes in the header row for per-column searching
 * Compatible with serverSide: true
 */

(function ($) {
  $.fn.dataTable.ColumnFilter = function (table) {
    let api = table.api();

    // Clone header for filters
    if (!$('#' + api.table().node().id + ' thead tr.filters').length) {
      $('#' + api.table().node().id + ' thead tr')
        .clone(true)
        .addClass('filters')
        .appendTo('#' + api.table().node().id + ' thead');
    }

    api.columns().eq(0).each(function (colIdx) {
      let cell = $('.filters th').eq(
        $(api.column(colIdx).header()).index()
      );
      $(cell).html('<input type="text" placeholder="Search" style="width:100%;" />');

      $('input', cell)
        .off('keyup change')
        .on('keyup change', function (e) {
          e.stopPropagation();

          api
            .column(colIdx)
            .search(this.value)
            .draw();
        });
    });
  };
})(jQuery);

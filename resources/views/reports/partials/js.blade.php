{{-- Shared helpers for report pages: client-side (aggregated JSON) and server-side (Yajra) DataTables --}}
<script>
    window.RPT = {
        tables: [],
        onReload: [],
        money: function (d, type) {
            return (type === 'display' || type === 'filter') ? APP.formatMoney(d) : (parseFloat(d) || 0);
        },
        qty: function (d, type) {
            return (type === 'display' || type === 'filter') ? APP.formatQty(d) : (parseFloat(d) || 0);
        },
        pct: function (d, type) {
            return (type === 'display' || type === 'filter') ? APP.round(d, 1) + '%' : (parseFloat(d) || 0);
        },
        sortable: { _: 'display', sort: 'sort' },
        params: function (section) {
            return $.extend(APP.filters('#filters_form'), section ? { section: section } : {});
        },
        /** Client-side DataTable fed by {data, totals} JSON. */
        client: function (selector, url, section, columns, opts) {
            opts = opts || {};
            var onData = opts.onData;
            delete opts.onData;
            var t = $(selector).DataTable($.extend({
                ajax: { url: url, data: function (d) { $.extend(d, RPT.params(section)); }, dataSrc: 'data' },
                columns: columns,
                order: [],
                deferRender: true
            }, opts));
            t.on('xhr', function (e, s, json) { APP.renderFooterTotals(selector, json); if (onData) { onData(json); } });
            RPT.tables.push(t);
            return t;
        },
        /** Server-side (Yajra) DataTable; json.totals feeds the footer / summary boxes. */
        server: function (selector, url, section, columns, opts) {
            opts = opts || {};
            var onData = opts.onData;
            delete opts.onData;
            var t = $(selector).DataTable($.extend({
                serverSide: true,
                ajax: { url: url, data: function (d) { $.extend(d, RPT.params(section)); } },
                columns: columns
            }, opts));
            t.on('xhr', function (e, s, json) { APP.renderFooterTotals(selector, json); if (onData) { onData(json); } });
            RPT.tables.push(t);
            return t;
        },
        /** Plain JSON fetch (no table) that fills [data-summary] elements. */
        fetch: function (url, section, cb) {
            var run = function () {
                $.getJSON(url, RPT.params(section)).done(function (json) {
                    APP.renderFooterTotals('#__none', json);
                    if (cb) { cb(json); }
                }).fail(function (xhr) { APP.handleError(xhr); });
            };
            RPT.onReload.push(run);
            run();
        },
        reload: function () {
            $.each(RPT.tables, function (i, t) { t.ajax.reload(); });
            $.each(RPT.onReload, function (i, fn) { fn(); });
        },
        init: function () {
            APP.initDateRange('#date_range', RPT.reload);
            $('#filters_form').on('change', 'select, input:not(.date-range-input)', RPT.reload);
            $('a[data-toggle="tab"]').on('shown.bs.tab', function () {
                $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust().responsive.recalc();
            });
        }
    };
</script>

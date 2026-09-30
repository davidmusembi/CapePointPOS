/* ==========================================================================
   CapePoint POS - common front-end behaviour (jQuery)
   ========================================================================== */
(function ($, window) {
    'use strict';

    var APP = window.APP = window.APP || {};
    var cur = APP.currency || { symbol: '', position: 'before', decimals: 2, thousand: ',', decimal: '.' };

    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' } });

    toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 3500, escapeHtml: true };

    /* ---------------------------------------------------------------------
     | Number / money helpers
     * ------------------------------------------------------------------- */
    APP.round = function (n, d) {
        d = d === undefined ? Math.min(2, cur.decimals) : d;
        var f = Math.pow(10, d);
        return Math.round((parseFloat(n) || 0) * f + (n >= 0 ? 1e-9 : -1e-9)) / f;
    };

    APP.formatNumber = function (n, decimals) {
        decimals = decimals === undefined ? cur.decimals : decimals;
        n = APP.round(n, decimals);
        var neg = n < 0, parts = Math.abs(n).toFixed(decimals).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, cur.thousand);
        return (neg ? '-' : '') + parts.join(cur.decimal);
    };

    APP.formatMoney = function (n, withSymbol) {
        n = APP.round(parseFloat(n) || 0, cur.decimals);
        var v = APP.formatNumber(Math.abs(n));
        if (withSymbol !== false) {
            v = cur.position === 'after' ? v + ' ' + cur.symbol : cur.symbol + ' ' + v;
        }
        return ((parseFloat(n) || 0) < 0 ? '-' : '') + v;
    };

    APP.formatQty = function (n) {
        n = APP.round(n, 3);
        return Number.isInteger(n) ? n.toLocaleString('en') : n.toLocaleString('en', { maximumFractionDigits: 3 });
    };

    APP.escape = function (s) {
        return $('<div>').text(s === null || s === undefined ? '' : String(s)).html();
    };

    /* ---------------------------------------------------------------------
     | DataTables defaults (server-side, export buttons fetch ALL rows)
     * ------------------------------------------------------------------- */
    function exportAllAction(type) {
        return function (e, dt, button, config) {
            var self = this;
            var settings = dt.settings()[0];
            var runOriginal = function () {
                var ext = $.fn.dataTable.ext.buttons;
                if (type === 'excel') { ext.excelHtml5.action.call(self, e, dt, button, config); }
                else if (type === 'csv') { ext.csvHtml5.action.call(self, e, dt, button, config); }
                else if (type === 'pdf') { ext.pdfHtml5.action.call(self, e, dt, button, config); }
                else if (type === 'print') { ext.print.action(e, dt, button, config); }
                else if (type === 'copy') { ext.copyHtml5.action.call(self, e, dt, button, config); }
            };
            if (!settings.oFeatures.bServerSide) { return runOriginal(); }

            var oldStart = settings._iDisplayStart;
            dt.one('preXhr', function (e, s, data) {
                data.start = 0;
                data.length = -1;
                dt.one('preDraw', function (e, s2) {
                    runOriginal();
                    dt.one('preXhr', function (e, s3, data2) {
                        s3._iDisplayStart = oldStart;
                        data2.start = oldStart;
                    });
                    setTimeout(function () { dt.ajax.reload(null, false); }, 0);
                    return false;
                });
            });
            dt.ajax.reload();
        };
    }

    var exportCols = { columns: ':visible:not(.no-export)', stripHtml: true, format: { body: function (d) { return typeof d === 'string' ? $('<div>').html(d).text().trim() : d; }, footer: function (d) { return $('<div>').html(d).text().trim(); } } };
    APP.dtTitle = function () { return (APP.businessName ? APP.businessName + ' - ' : '') + ($('.content-header h1').first().clone().children().remove().end().text().trim() || document.title); };

    APP.dtButtons = [
        { extend: 'copyHtml5', text: '<i class="far fa-copy"></i> Copy', className: 'btn-sm', exportOptions: exportCols, footer: true, action: exportAllAction('copy') },
        { extend: 'csvHtml5', text: '<i class="fas fa-file-csv"></i> CSV', className: 'btn-sm', exportOptions: exportCols, footer: true, title: APP.dtTitle, action: exportAllAction('csv') },
        { extend: 'excelHtml5', text: '<i class="far fa-file-excel"></i> Excel', className: 'btn-sm', exportOptions: exportCols, footer: true, title: APP.dtTitle, action: exportAllAction('excel') },
        { extend: 'pdfHtml5', text: '<i class="far fa-file-pdf"></i> PDF', className: 'btn-sm', exportOptions: exportCols, footer: true, title: APP.dtTitle, orientation: 'landscape', pageSize: 'A4', action: exportAllAction('pdf'),
            customize: function (doc) {
                doc.defaultStyle.fontSize = 8;
                doc.styles.tableHeader.fontSize = 8;
                doc.styles.tableHeader.fillColor = '#1b4f8a';
                doc.styles.tableFooter = { bold: true, fontSize: 8, fillColor: '#eef2f7' };
                doc.styles.title = { fontSize: 13, bold: true, color: '#1b4f8a', margin: [0, 0, 0, 8] };
                var table = doc.content[doc.content.length - 1].table;
                if (table && table.body[0]) { table.widths = Array(table.body[0].length + 1).join('*').split(''); }
            } },
        { extend: 'print', text: '<i class="fas fa-print"></i> Print', className: 'btn-sm', exportOptions: exportCols, footer: true, title: APP.dtTitle, action: exportAllAction('print') },
        { extend: 'colvis', text: '<i class="fas fa-columns"></i> Columns', className: 'btn-sm' }
    ];

    $.extend(true, $.fn.dataTable.defaults, {
        processing: true,
        responsive: true,
        autoWidth: false,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
        dom: '<"row dt-toolbar"<"col-md-3 col-sm-12"l><"col-md-6 col-sm-12 text-center"B><"col-md-3 col-sm-12"f>>rt<"row"<"col-md-5"i><"col-md-7"p>>',
        buttons: APP.dtButtons,
        order: [],
        language: {
            processing: '<i class="fas fa-circle-notch fa-spin mr-2"></i> Loading...',
            search: '',
            searchPlaceholder: 'Search...',
            lengthMenu: 'Show _MENU_',
            emptyTable: 'No records found',
            zeroRecords: 'No matching records found'
        }
    });
    $.fn.dataTable.ext.errMode = function (settings, tn, message) { console.error(message); toastr.error('Could not load table data.'); };

    /** Put server-provided footer totals (json.totals) into <tfoot> cells with data-total="key". */
    APP.renderFooterTotals = function (tableSelector, json) {
        if (!json || !json.totals) { return; }
        $(tableSelector).closest('.dataTables_wrapper').find('[data-total]').add($(tableSelector).find('tfoot [data-total]')).each(function () {
            var key = $(this).data('total');
            if (json.totals[key] !== undefined) {
                $(this).html($(this).data('format') === 'qty' ? APP.formatQty(json.totals[key]) : ($(this).data('format') === 'raw' ? json.totals[key] : APP.formatMoney(json.totals[key])));
            }
        });
        $('[data-summary]').each(function () {
            var key = $(this).data('summary');
            if (json.totals[key] !== undefined) {
                $(this).html($(this).data('format') === 'raw' ? json.totals[key] : ($(this).data('format') === 'qty' ? APP.formatQty(json.totals[key]) : APP.formatMoney(json.totals[key])));
            }
        });
    };

    APP.reloadTables = function () {
        $('table.dataTable').each(function () {
            var dt = $(this).DataTable();
            if (dt.settings()[0].ajax) { dt.ajax.reload(null, false); }
        });
    };

    /* ---------------------------------------------------------------------
     | Date range picker (filters)
     * ------------------------------------------------------------------- */
    /** Start of the financial year containing `date` (start month from business settings, 1-12). */
    APP.financialYearStart = function (date) {
        var m = (parseInt(APP.financialYearStartMonth, 10) || 1) - 1;
        var start = moment(date).month(m).startOf('month');
        return start.isAfter(moment(date)) ? start.subtract(1, 'year') : start;
    };

    APP.dateRanges = function () {
        var fyStart = APP.financialYearStart(moment());
        return {
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
            'This month last year': [moment().subtract(1, 'year').startOf('month'), moment().subtract(1, 'year').endOf('month')],
            'This Year': [moment().startOf('year'), moment().endOf('year')],
            'Last Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
            'Current financial year': [fyStart.clone(), fyStart.clone().add(1, 'year').subtract(1, 'day')],
            'Last financial year': [fyStart.clone().subtract(1, 'year'), fyStart.clone().subtract(1, 'day')]
        };
    };

    /**
     * Initialise a date-range input. Writes Y-m-d values into [name=start_date] / [name=end_date] in the same form.
     */
    APP.initDateRange = function (selector, onChange, opts) {
        var $el = $(selector);
        if (!$el.length) { return; }
        var $form = $el.closest('form, .filters');
        var $start = $form.find('[name="start_date"]'), $end = $form.find('[name="end_date"]');
        var start = $start.val() ? moment($start.val()) : moment().startOf('month');
        var end = $end.val() ? moment($end.val()) : moment();
        opts = $.extend({ startDate: start, endDate: end, ranges: APP.dateRanges(), alwaysShowCalendars: true, opens: 'right', locale: { format: APP.momentDateFormat || 'DD/MM/YYYY', cancelLabel: 'Clear' } }, opts || {});

        var apply = function (s, e) {
            $el.val(s.format(opts.locale.format) + ' ~ ' + e.format(opts.locale.format));
            $start.val(s.format('YYYY-MM-DD'));
            $end.val(e.format('YYYY-MM-DD'));
        };
        $el.daterangepicker(opts, function (s, e) { apply(s, e); if (onChange) { onChange(s, e); } });
        apply(start, end);
        $el.on('cancel.daterangepicker', function () {
            $start.val('2000-01-01'); $end.val(moment().add(1, 'year').format('YYYY-MM-DD'));
            $el.val('All dates');
            if (onChange) { onChange(); }
        });
    };

    /** Serialise a filter form into an object for DataTables ajax.data */
    APP.filters = function (formSelector) {
        var out = {};
        $.each($(formSelector).serializeArray(), function (i, f) { out[f.name] = f.value; });
        return out;
    };

    /* ---------------------------------------------------------------------
     | Select2
     * ------------------------------------------------------------------- */
    APP.initSelect2 = function ($scope) {
        $scope = $scope || $(document);
        $scope.find('select.select2').each(function () {
            var $s = $(this);
            if ($s.data('select2')) { return; }
            var $modal = $s.closest('.modal');
            $s.select2({
                theme: 'bootstrap4',
                width: '100%',
                allowClear: !!$s.data('allow-clear'),
                placeholder: $s.data('placeholder') || $s.find('option[value=""]').text() || 'Select',
                dropdownParent: $modal.length ? $modal : $(document.body)
            });
        });
    };

    /** Ajax-backed select2 for contacts (customers / suppliers). */
    APP.contactSelect = function (selector, url, opts) {
        var $s = $(selector);
        var $modal = $s.closest('.modal');
        return $s.select2($.extend({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: $s.data('placeholder') || 'Search by name, code or phone',
            allowClear: !!$s.data('allow-clear'),
            dropdownParent: $modal.length ? $modal : $(document.body),
            ajax: {
                url: url, dataType: 'json', delay: 250,
                data: function (p) { return { q: p.term, page: p.page || 1 }; },
                processResults: function (d) { return { results: d.results, pagination: { more: d.more } }; }
            },
            templateResult: function (item) {
                if (!item.id) { return item.text; }
                var bal = item.balance !== undefined ? '<small class="float-right">Bal: ' + APP.formatMoney(item.balance) + '</small>' : '';
                return $('<div class="select2-result-product">' + bal + APP.escape(item.text) + (item.phone ? '<br><small>' + APP.escape(item.code || '') + ' &middot; ' + APP.escape(item.phone) + '</small>' : '<br><small>' + APP.escape(item.code || '') + '</small>') + '</div>');
            },
            templateSelection: function (item) { return item.text; }
        }, opts || {}));
    };

    /* ---------------------------------------------------------------------
     | Modals loaded over AJAX  (<a class="btn-modal" data-href="..." data-container="#app_modal">)
     * ------------------------------------------------------------------- */
    $(document).on('click', '.btn-modal', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var container = $btn.data('container') || '#app_modal';
        var url = $btn.data('href') || $btn.attr('href');
        $.ajax({ url: url, dataType: 'html', headers: { 'Accept': 'text/html' } })
            .done(function (html) {
                $(container).html(html).modal('show');
                APP.initSelect2($(container));
                $(container).trigger('modal:loaded');
            })
            .fail(function (xhr) { APP.handleError(xhr); });
    });

    $(document).on('shown.bs.modal', '.modal', function () {
        $(this).find('input:visible:not([readonly]):first').trigger('focus');
    });

    /* ---------------------------------------------------------------------
     | AJAX forms  (<form class="ajax-form"> ... returns {success, message, redirect?})
     * ------------------------------------------------------------------- */
    APP.clearErrors = function ($form) {
        $form.find('.is-invalid').removeClass('is-invalid');
        $form.find('.invalid-feedback.ajax-error').remove();
        $form.find('.alert-ajax-error').remove();
    };

    APP.showErrors = function ($form, errors) {
        var unplaced = [];
        $.each(errors || {}, function (field, messages) {
            var name = field.replace(/\.(\w+)/g, '[$1]');
            var $input = $form.find('[name="' + name + '"], [name="' + name + '[]"]').first();
            if ($input.length) {
                $input.addClass('is-invalid');
                var $target = $input.closest('.input-group').length ? $input.closest('.input-group') : ($input.next('.select2').length ? $input.next('.select2') : $input);
                $target.after('<div class="invalid-feedback ajax-error d-block">' + APP.escape(messages[0]) + '</div>');
            } else {
                unplaced = unplaced.concat(messages);
            }
        });
        if (unplaced.length) {
            var html = '<div class="alert alert-danger alert-ajax-error"><ul class="mb-0 pl-3">' + unplaced.map(function (m) { return '<li>' + APP.escape(m) + '</li>'; }).join('') + '</ul></div>';
            var $body = $form.find('.modal-body').first();
            ($body.length ? $body : $form).prepend(html);
        }
    };

    APP.handleError = function (xhr, $form) {
        var res = xhr.responseJSON || {};
        if (xhr.status === 422 && $form) {
            APP.showErrors($form, res.errors);
        }
        toastr.error(res.message || (xhr.status === 403 ? 'You do not have permission to perform this action.' : 'Something went wrong. Please try again.'));
    };

    $(document).on('submit', 'form.ajax-form', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('[type="submit"]');
        APP.clearErrors($form);
        $btn.prop('disabled', true).data('html', $btn.html()).html('<i class="fas fa-circle-notch fa-spin"></i> Saving...');

        $.ajax({ url: $form.attr('action'), method: 'POST', data: new FormData(this), processData: false, contentType: false })
            .done(function (res) {
                if (res.success === false) { toastr.error(res.message); return; }
                toastr.success(res.message || 'Saved successfully');
                var $modal = $form.closest('.modal');
                if ($modal.length) { $modal.modal('hide'); }
                if (res.redirect) { window.location = res.redirect; return; }
                if ($form.data('reload') === 'page') { window.location.reload(); return; }
                APP.reloadTables();
                $form.trigger('ajax:success', [res]);
                $(document).trigger('app:saved', [res, $form]);
            })
            .fail(function (xhr) { APP.handleError(xhr, $form); })
            .always(function () { $btn.prop('disabled', false).html($btn.data('html')); });
    });

    /* ---------------------------------------------------------------------
     | Delete / confirm actions  (<a class="btn-delete" data-href="..." data-redirect="...">)
     * ------------------------------------------------------------------- */
    APP.confirm = function (opts) {
        return Swal.fire($.extend({
            title: 'Are you sure?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7686',
            confirmButtonText: 'Yes, continue',
            reverseButtons: true
        }, opts || {}));
    };

    $(document).on('click', '.btn-delete, .btn-confirm', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var isDelete = $btn.hasClass('btn-delete');
        APP.confirm({
            text: $btn.data('message') || (isDelete ? 'This record will be deleted.' : 'Please confirm this action.'),
            confirmButtonText: $btn.data('confirm') || (isDelete ? 'Yes, delete' : 'Yes, continue'),
            confirmButtonColor: isDelete ? '#dc2626' : '#1b4f8a'
        }).then(function (r) {
            if (!r.isConfirmed) { return; }
            $.ajax({ url: $btn.data('href'), method: 'POST', data: { _method: $btn.data('method') || (isDelete ? 'DELETE' : 'PATCH') } })
                .done(function (res) {
                    if (res.success === false) { toastr.error(res.message); return; }
                    toastr.success(res.message || 'Done');
                    if ($btn.data('redirect')) { window.location = $btn.data('redirect'); return; }
                    if (res.redirect) { window.location = res.redirect; return; }
                    if ($btn.data('reload') === 'page') { window.location.reload(); return; }
                    APP.reloadTables();
                })
                .fail(function (xhr) { APP.handleError(xhr); });
        });
    });

    /* Prevent double submit on standard forms */
    $(document).on('submit', 'form:not(.ajax-form):not(.no-lock)', function () {
        var $btn = $(this).find('[type="submit"]');
        setTimeout(function () { $btn.prop('disabled', true); }, 0);
    });

    /* ---------------------------------------------------------------------
     | Notification bell (navbar)
     * ------------------------------------------------------------------- */
    APP.notifications = (function () {
        var $menu, filter = 'unread';

        function setCount(n) {
            $menu.find('.notif-count').text(n > 99 ? '99+' : n).toggleClass('d-none', !n);
            $menu.find('.notif-unread-num').text(n);
        }

        function render(items) {
            var $list = $menu.find('.notif-list');
            if (!items.length) {
                $list.html('<div class="notif-empty"><i class="far fa-bell-slash d-block mb-2" style="font-size:1.6rem"></i>' +
                    (filter === 'unread' ? 'You are all caught up.' : 'No notifications yet.') + '</div>');
                return;
            }
            $list.html($.map(items, function (n) {
                return '<div class="notif-item' + (n.read ? '' : ' unread') + '" data-id="' + APP.escape(n.id) + '" data-url="' + APP.escape(n.url || '') + '">' +
                    '<div class="notif-icon tone-' + APP.escape(n.tone) + '"><i class="' + APP.escape(n.icon) + '"></i></div>' +
                    '<div class="notif-body"><div class="notif-title">' + APP.escape(n.title) + '</div>' +
                    '<div class="notif-msg">' + APP.escape(n.message) + '</div>' +
                    '<div class="notif-meta">' + APP.escape(n.time || '') + (n.context ? ' &bull; ' + APP.escape(n.context) : '') + '</div></div>' +
                    (n.read ? '' : '<button type="button" class="notif-dot" title="Mark as read" aria-label="Mark as read"></button>') +
                    '</div>';
            }).join(''));
        }

        function load() {
            $menu.find('.notif-list').html('<div class="notif-empty"><i class="fas fa-circle-notch fa-spin"></i></div>');
            $.getJSON($menu.data('url'), { filter: filter }, function (res) { setCount(res.unread); render(res.items); });
        }

        function markRead(id, done) {
            $.post($menu.data('read').replace('__ID__', encodeURIComponent(id)), function (res) { setCount(res.unread); if (done) { done(res); } });
        }

        function init() {
            $menu = $('#notif_menu');
            if (!$menu.length) { return; }
            // Clicks inside the panel don't bubble past it (keeps the dropdown open), so bind there.
            var $panel = $menu.find('.notif-panel');
            $menu.on('show.bs.dropdown', load);
            $panel.on('click', '.notif-tabs a', function (e) {
                e.preventDefault();
                filter = $(this).data('filter');
                $(this).addClass('active').siblings().removeClass('active');
                load();
            });
            $panel.on('click', '.notif-read-all', function (e) {
                e.preventDefault();
                $.post($menu.data('read-all'), function () { setCount(0); load(); });
            });
            $panel.on('click', '.notif-dot', function (e) {
                e.stopPropagation();
                var $item = $(this).closest('.notif-item');
                markRead($item.data('id'), function () {
                    if (filter === 'unread') { $item.slideUp(150, function () { $(this).remove(); if (!$menu.find('.notif-item').length) { render([]); } }); }
                    else { $item.removeClass('unread').find('.notif-dot').remove(); }
                });
            });
            $panel.on('click', '.notif-item', function () {
                var url = $(this).data('url');
                markRead($(this).data('id'), function () { if (url) { window.location = url; } });
            });
        }

        return { init: init, reload: load };
    })();

    $(function () {
        APP.notifications.init();
        APP.initSelect2();
        $('[data-toggle="tooltip"]').tooltip();
        if (APP.flash) {
            $.each(APP.flash, function (type, msg) { if (msg) { toastr[type](msg); } });
        }
    });
})(jQuery, window);

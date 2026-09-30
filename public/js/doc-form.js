/* ==========================================================================
   Line-item document form (Sales invoice, LPO, Purchase invoice)
   Usage: DocForm.init({ priceName: 'unit_price', priceField: 'selling_price', checkStock: true,
                         searchUrl: '...', taxRates: [{rate: 16, name: 'VAT'}], items: [...] })
   ========================================================================== */
(function ($, window) {
    'use strict';

    var DocForm = window.DocForm = {};
    var opts, index = 0;

    function taxOptions(selected) {
        var html = '<option value="0">None</option>';
        var found = parseFloat(selected) === 0;
        $.each(opts.taxRates, function (i, t) {
            var sel = !found && parseFloat(t.rate) === parseFloat(selected);
            if (sel) { found = true; }
            html += '<option value="' + t.rate + '"' + (sel ? ' selected' : '') + '>' + APP.escape(t.name) + ' (' + parseFloat(t.rate) + '%)</option>';
        });
        if (!found && selected) {
            html += '<option value="' + selected + '" selected>' + parseFloat(selected) + '%</option>';
        }
        return html;
    }

    DocForm.addRow = function (p) {
        var $tbody = $('#items_table tbody');
        // Merge with an existing row for the same product (not for LPO-linked rows)
        if (!p.purchase_order_item_id && !p.force_new) {
            var $existing = $tbody.find('tr.item-row[data-product-id="' + p.id + '"]').first();
            if ($existing.length && !$existing.find('.poi-id').val()) {
                var $q = $existing.find('.qty');
                $q.val(APP.round((parseFloat($q.val()) || 0) + 1, 3)).trigger('input');
                $q.trigger('focus').select();
                return;
            }
        }

        $tbody.find('tr.empty-row').remove();
        var i = index++;
        var name = 'items[' + i + ']';
        var price = p.price !== undefined ? p.price : (p[opts.priceField] || 0);
        var qty = p.quantity !== undefined ? p.quantity : 1;
        var tax = p.tax_rate_line !== undefined ? p.tax_rate_line : (p.tax_rate || 0);
        var stockInfo = opts.checkStock && p.track_stock ? ' &middot; <span class="stock-info">In stock: ' + APP.formatQty(p.stock) + ' ' + APP.escape(p.unit || '') + '</span>' : '';
        var extra = p.pending !== undefined ? ' &middot; LPO pending: ' + APP.formatQty(p.pending) : '';

        var row = '' +
            '<tr class="item-row" data-product-id="' + p.id + '" data-stock="' + (p.stock || 0) + '" data-track="' + (p.track_stock ? 1 : 0) + '">' +
            '<td class="text-center row-no"></td>' +
            '<td>' +
                '<div class="product-name">' + APP.escape(p.text || p.name) + '</div>' +
                '<div class="product-meta">' + APP.escape(p.sku || '') + stockInfo + extra + '</div>' +
                '<input type="hidden" name="' + name + '[product_id]" value="' + p.id + '">' +
                (p.purchase_order_item_id ? '<input type="hidden" class="poi-id" name="' + name + '[purchase_order_item_id]" value="' + p.purchase_order_item_id + '">' : '') +
                (opts.withDescription ? '<input type="text" class="form-control form-control-sm mt-1" name="' + name + '[description]" placeholder="Description (optional)" value="' + APP.escape(p.description || '') + '">' : '') +
            '</td>' +
            '<td style="width:110px"><input type="number" step="any" min="0.001" class="form-control qty text-right" name="' + name + '[quantity]" value="' + qty + '" required></td>' +
            '<td style="width:140px"><input type="number" step="any" min="0" class="form-control price text-right" name="' + name + '[' + opts.priceName + ']" value="' + APP.round(price, opts.priceDecimals || 2) + '" required></td>' +
            '<td style="width:95px"><input type="number" step="any" min="0" max="100" class="form-control disc text-right" name="' + name + '[discount_percent]" value="' + (p.discount_percent || 0) + '"><div class="line-disc-amount small text-danger text-right"></div></td>' +
            '<td style="width:150px"><select class="form-control tax custom-select" name="' + name + '[tax_rate]">' + taxOptions(tax) + '</select></td>' +
            '<td style="width:140px" class="text-right line-total font-weight-600">0.00</td>' +
            '<td style="width:36px" class="text-center"><i class="fas fa-times-circle remove-row" title="Remove"></i></td>' +
            '</tr>';

        $tbody.append(row);
        DocForm.recalc();
        if (!p.silent) { $tbody.find('tr.item-row:last .qty').trigger('focus').select(); }
    };

    /*
     * Mirrors App\Services\DocumentTotals (keep in sync):
     * line discount % first, then the document discount (fixed or % of subtotal) is allocated pro-rata
     * to lines BEFORE tax; tax is charged on each line's discounted net.
     */
    DocForm.recalc = function () {
        var rows = [], subtotal = 0, n = 0;
        $('#items_table tbody tr.item-row').each(function () {
            var $r = $(this);
            n++;
            $r.find('.row-no').text(n);
            var q = APP.round(parseFloat($r.find('.qty').val()) || 0, 3);
            var p = parseFloat($r.find('.price').val()) || 0;
            var d = Math.min(100, Math.max(0, parseFloat($r.find('.disc').val()) || 0));
            var t = Math.min(100, Math.max(0, parseFloat($r.find('.tax').val()) || 0));
            var gross = APP.round(q * p);
            var lineDisc = APP.round(gross * d / 100);
            var after = APP.round(gross - lineDisc);
            rows.push({ $r: $r, rate: t, lineDisc: lineDisc, after: after });
            subtotal += after;

            if (opts.checkStock && $r.data('track') == 1) {
                var over = q > parseFloat($r.data('stock'));
                $r.toggleClass('table-warning', over);
                $r.find('.stock-info').toggleClass('text-danger font-weight-bold', over);
            }
        });

        if (!n && !$('#items_table tbody tr.empty-row').length) {
            $('#items_table tbody').html('<tr class="empty-row"><td colspan="8"><i class="fas fa-box-open mr-1"></i> Search and add products above</td></tr>');
        }

        subtotal = APP.round(subtotal);
        var dType = $('#discount_type').val();
        var dVal = Math.max(0, parseFloat($('#discount_value').val()) || 0);
        var discount = dType === 'percentage' ? APP.round(subtotal * Math.min(100, dVal) / 100) : APP.round(Math.min(dVal, subtotal));

        // allocate the document discount; the last non-zero line absorbs rounding
        var lastIdx = -1;
        $.each(rows, function (i, r) { if (r.after > 0) { lastIdx = i; } });
        var allocated = 0, tax = 0;
        $.each(rows, function (i, r) {
            var share = 0;
            if (discount > 0 && subtotal > 0 && r.after > 0) {
                share = i === lastIdx ? APP.round(discount - allocated) : APP.round(discount * r.after / subtotal);
                allocated = APP.round(allocated + share);
            }
            var net = APP.round(r.after - share);
            var lineTax = APP.round(net * r.rate / 100);
            tax += lineTax;
            r.$r.find('.line-total').text(APP.formatNumber(net + lineTax));
            var totalDisc = APP.round(r.lineDisc + share);
            r.$r.find('.line-disc-amount').text(totalDisc > 0 ? '- ' + APP.formatNumber(totalDisc) : '');
        });
        tax = APP.round(tax);

        // Shipping + additional charges (sales invoice only) are added after tax, like SaleService.
        var shipping = APP.round(Math.max(0, parseFloat($('#shipping_charges').val()) || 0));
        var extra = 0;
        $('.additional-charge-amount').each(function () { extra += Math.max(0, parseFloat($(this).val()) || 0); });
        extra = APP.round(extra);
        $('#shipping_text').text(APP.formatMoney(shipping));
        $('#charges_text').text(APP.formatMoney(extra));

        var total = APP.round(subtotal - discount + tax + shipping + extra);

        $('#subtotal_text').text(APP.formatMoney(subtotal));
        $('#tax_text').text(APP.formatMoney(tax));
        $('#discount_text').text('- ' + APP.formatMoney(discount));
        $('#total_text').text(APP.formatMoney(total));
        $('#item_count').text(n);
        DocForm.total = total;

        // payment rows (split payments)
        if ($('#payment_rows').length) {
            // include payments allocated from the Payments module (read-only on the form)
            var paid = parseFloat($('#payment_rows').data('offset')) || 0;
            $('#payment_rows .pay-amount').each(function () { paid += Math.max(0, parseFloat($(this).val()) || 0); });
            paid = APP.round(paid);
            var balance = APP.round(total - paid);
            $('#paying_text').text(APP.formatMoney(paid)).toggleClass('text-danger', paid - total > 0.009);
            $('#balance_text').text(APP.formatMoney(balance)).toggleClass('text-danger', balance > 0).toggleClass('text-success', balance <= 0);
            var status = paid <= 0 ? 'Due' : (balance <= 0 ? 'Paid' : 'Partial');
            var cls = paid <= 0 ? 'danger' : (balance <= 0 ? 'success' : 'warning');
            $('#payment_status_preview').attr('class', 'badge badge-pill badge-' + cls).text(status);
        }
    };

    DocForm.init = function (options) {
        opts = $.extend({ priceName: 'unit_price', priceField: 'selling_price', checkStock: false, withDescription: false, taxRates: [], items: [] }, options);

        var $search = $('#product_search');
        $search.select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'Search product by name, SKU or barcode...',
            minimumInputLength: 0,
            ajax: {
                url: opts.searchUrl, dataType: 'json', delay: 200,
                data: function (p) { return { q: p.term, page: p.page || 1 }; },
                processResults: function (d) { return { results: d.results, pagination: { more: d.more } }; }
            },
            templateResult: function (p) {
                if (p.loading) { return p.text; }
                var stock = p.track_stock ? 'Stock: ' + APP.formatQty(p.stock) + ' ' + APP.escape(p.unit || '') : 'Not stock-tracked';
                return $('<div class="select2-result-product"><span class="float-right">' + APP.formatMoney(p[opts.priceField]) + '</span><strong>' + APP.escape(p.text) + '</strong><br><small>' + APP.escape(p.sku) + ' &middot; ' + stock + '</small></div>');
            }
        }).on('select2:select', function (e) {
            DocForm.addRow(e.params.data);
            $search.val(null).trigger('change');
        });

        $(document).on('input change', '#items_table .qty, #items_table .price, #items_table .disc, #items_table .tax, #discount_type, #discount_value, #payment_rows .pay-amount, #shipping_charges, .additional-charge-amount', DocForm.recalc);
        $(document).on('click', '#items_table .remove-row', function () { $(this).closest('tr').remove(); DocForm.recalc(); });

        $.each(opts.items, function (i, it) { it.silent = true; it.force_new = true; DocForm.addRow(it); });
        DocForm.recalc();

        $('#doc-form').on('submit', function (e) {
            if (!$('#items_table tbody tr.item-row').length) {
                e.preventDefault();
                toastr.error('Add at least one product.');
                setTimeout(function () { $('#doc-form [type=submit]').prop('disabled', false); }, 10);
                return false;
            }
        });

        // Keyboard: Enter on qty/price moves focus to product search instead of submitting
        $(document).on('keydown', '#items_table input', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); $search.select2('open'); }
        });
    };
})(jQuery, window);

<script>
    $(function () {
        APP.contactSelect('#filter_customer', '{{ route('customers.search') }}');
        APP.initDateRange('#date_range', function () { table.ajax.reload(); });

        var columns = [
            { data: 'date', name: 'sales.date' },
            { data: 'invoice_no', name: 'sales.invoice_no' },
            { data: 'customer_name', name: 'customer_name', orderable: false },
            { data: 'total', name: 'sales.total', className: 'text-right', searchable: false },
            { data: 'paid_amount', name: 'sales.paid_amount', className: 'text-right', searchable: false },
            { data: 'returned_amount', name: 'sales.returned_amount', className: 'text-right', searchable: false },
            { data: 'due_amount', name: 'sales.due_amount', className: 'text-right', searchable: false },
            { data: 'payment_status', name: 'sales.payment_status', className: 'text-center', searchable: false },
            { data: 'shipping_status', name: 'sales.shipping_status', className: 'text-center', searchable: false },
            { data: 'due_date', name: 'sales.due_date', searchable: false }
        ];
        @if ($withOverdue)
        columns.push({ data: 'days_overdue', name: 'days_overdue', orderable: false, searchable: false, className: 'text-center' });
        @endif
        columns.push({ data: 'added_by', name: 'added_by', orderable: false, searchable: false });
        columns.push({ data: 'action', name: 'action', orderable: false, searchable: false });

        var table = $('#{{ $tableId }}').DataTable({
            serverSide: true,
            ajax: { url: '{{ $url }}', data: function (d) { $.extend(d, APP.filters('#filters_form')); } },
            order: [[0, 'desc']],
            columns: columns,
            drawCallback: function () { APP.renderFooterTotals('#{{ $tableId }}', this.api().ajax.json()); }
        });

        $('#filters_form').on('change', 'select, input:not(.date-range-input)', function () { table.ajax.reload(); });
    });
</script>

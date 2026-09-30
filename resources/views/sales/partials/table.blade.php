<table class="table table-bordered table-striped table-hover w-100" id="{{ $tableId }}">
    <thead>
    <tr>
        <th>Date</th>
        <th>Invoice No</th>
        <th>Customer</th>
        <th>Total</th>
        <th>Paid</th>
        <th>Returned</th>
        <th>Due</th>
        <th>Status</th>
        <th>Due Date</th>
        @if ($withOverdue)<th>Overdue</th>@endif
        <th>Added By</th>
        <th class="no-export" style="width:90px">Action</th>
    </tr>
    </thead>
    <tfoot>
    <tr>
        <th colspan="3" class="text-right">Total:</th>
        <th class="text-right" data-total="total"></th>
        <th class="text-right" data-total="paid"></th>
        <th class="text-right" data-total="returned"></th>
        <th class="text-right" data-total="due"></th>
        <th></th>
        <th></th>
        @if ($withOverdue)<th></th>@endif
        <th></th>
        <th></th>
    </tr>
    </tfoot>
</table>

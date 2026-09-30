@extends('layouts.app')

@section('title', 'New Sales Return')

@section('header_actions')
    <a href="{{ route('sale-returns.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Back</a>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title">Select the invoice being returned</h3></div>
                <div class="card-body">
                    <form method="GET" action="{{ route('sale-returns.create') }}" class="no-lock">
                        <div class="form-group">
                            <label class="required" for="sale_id">Sales invoice</label>
                            <select name="sale_id" id="sale_id" class="form-control" required></select>
                            <small class="text-muted">Search by invoice number or customer name. Only invoices with returnable items are listed.</small>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-arrow-right"></i> Continue</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        $('#sale_id').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'Search invoice...',
            ajax: {
                url: '{{ route('sale-returns.index') }}', dataType: 'json', delay: 250,
                data: function (p) { return { lookup: 1, q: p.term, page: p.page || 1 }; },
                processResults: function (d) { return { results: d.results, pagination: { more: d.more } }; }
            }
        }).on('select2:select', function () { $(this).closest('form').submit(); });
    });
</script>
@endpush

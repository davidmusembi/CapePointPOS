@props(['id' => 'filters_form', 'title' => 'Filters', 'collapsed' => false])
{{-- Collapsible filter box shown at the top of listings and reports --}}
<div class="card card-filter {{ $collapsed ? 'collapsed-card' : '' }} no-print">
    <div class="card-header" data-card-widget="collapse">
        <h3 class="card-title"><i class="fas fa-filter mr-1"></i> {{ $title }}</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas {{ $collapsed ? 'fa-plus' : 'fa-minus' }}"></i></button>
        </div>
    </div>
    <div class="card-body pb-1">
        <form id="{{ $id }}" class="filters" onsubmit="return false;">
            <div class="row">
                {{ $slot }}
            </div>
        </form>
    </div>
</div>

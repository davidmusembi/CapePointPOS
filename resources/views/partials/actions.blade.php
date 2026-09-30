@php
    $items = array_values(array_filter($items, fn ($i) => $i === '-' || empty($i['can']) || auth()->user()->can($i['can'])));
    // trim leading / trailing / duplicate dividers
    $clean = [];
    foreach ($items as $i) {
        if ($i === '-' && (empty($clean) || end($clean) === '-')) continue;
        $clean[] = $i;
    }
    if (end($clean) === '-') array_pop($clean);
@endphp
@if ($clean)
<div class="btn-group">
    <button type="button" class="btn btn-xs btn-actions dropdown-toggle" data-toggle="dropdown" data-boundary="window" aria-expanded="false">
        Actions
    </button>
    <div class="dropdown-menu dropdown-menu-right">
        @foreach ($clean as $item)
            @if ($item === '-')
                <div class="dropdown-divider"></div>
            @elseif (isset($item['modal']))
                <a href="#" class="dropdown-item btn-modal" data-href="{{ $item['modal'] }}" data-container="{{ $item['container'] ?? '#app_modal' }}"><i class="{{ $item['icon'] }}"></i> {{ $item['label'] }}</a>
            @elseif (isset($item['delete']))
                <a href="#" class="dropdown-item text-danger btn-delete" data-href="{{ $item['delete'] }}" @isset($item['message']) data-message="{{ $item['message'] }}" @endisset @if (! empty($item['reload'])) data-reload="page" @endif><i class="{{ $item['icon'] ?? 'fas fa-trash-alt' }} text-danger"></i> {{ $item['label'] ?? 'Delete' }}</a>
            @elseif (isset($item['confirm']))
                <a href="#" class="dropdown-item btn-confirm" data-href="{{ $item['confirm'] }}" data-method="{{ $item['method'] ?? 'PATCH' }}" @isset($item['message']) data-message="{{ $item['message'] }}" @endisset><i class="{{ $item['icon'] }}"></i> {{ $item['label'] }}</a>
            @else
                <a href="{{ $item['url'] }}" class="dropdown-item" @if (! empty($item['blank'])) target="_blank" @endif><i class="{{ $item['icon'] }}"></i> {{ $item['label'] }}</a>
            @endif
        @endforeach
    </div>
</div>
@endif

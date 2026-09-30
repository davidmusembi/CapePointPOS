{{-- Contact filter: $contacts (collection), $name (field), $label --}}
<div class="{{ $col ?? 'col-md-3' }}">
    <div class="form-group">
        <label>{{ $label }}</label>
        <select name="{{ $name }}" class="form-control select2" data-allow-clear="true" data-placeholder="All {{ strtolower(\Illuminate\Support\Str::plural($label)) }}">
            <option value=""></option>
            @foreach ($contacts as $c)
                <option value="{{ $c->id }}">{{ $c->name }}{{ $c->company ? ' ('.$c->company.')' : '' }} - {{ $c->code }}</option>
            @endforeach
        </select>
    </div>
</div>

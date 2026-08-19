@php
    $field['value'] = old_empty_or_null($field['name'], $field['value'] ?? $field['default'] ?? []);
    $selectedDays = is_array($field['value']) ? $field['value'] : (array) $field['value'];
    $hasError = $errors->has($field['name']) || $errors->has($field['name'].'.*');
@endphp

@include('crud::fields.inc.wrapper_start')
    <label>{!! $field['label'] !!}</label>

    <div class="row g-2">
        @foreach(($field['options'] ?? []) as $value => $label)
            <div class="col-sm-6 col-lg-4">
                <div class="form-check border rounded px-3 py-2 h-100">
                    <input
                        type="checkbox"
                        name="{{ $field['name'] }}[]"
                        id="{{ $field['name'] }}_{{ $value }}"
                        value="{{ $value }}"
                        class="form-check-input {{ $hasError ? 'is-invalid' : '' }}"
                        @checked(in_array($value, $selectedDays, true))
                    >
                    <label class="form-check-label" for="{{ $field['name'] }}_{{ $value }}">{{ $label }}</label>
                </div>
            </div>
        @endforeach
    </div>

    @if ($hasError)
        <div class="invalid-feedback d-block">
            {{ $errors->first($field['name']) ?: $errors->first($field['name'].'.*') }}
        </div>
    @endif

    @if (isset($field['hint']))
        <p class="help-block">{!! $field['hint'] !!}</p>
    @endif
@include('crud::fields.inc.wrapper_end')

@php($inputId = str_replace(['[', ']', '.'], ['_', '', '_'], $name))
<div class="form-field {{ $wide ?? false ? 'form-field-wide' : '' }}">
    <label for="{{ $inputId }}">{{ $label }} @if ($required ?? false)
            <span aria-hidden="true">*</span>
        @endif
    </label>
    @if (($kind ?? 'text') === 'textarea')
        <textarea id="{{ $inputId }}" name="{{ $name }}" rows="{{ $rows ?? 4 }}" @required($required ?? false)>{{ old($name, $value ?? '') }}</textarea>
    @elseif(($kind ?? 'text') === 'select')
        <select id="{{ $inputId }}" name="{{ $name }}"
            @if ($lookup ?? null) data-lookup="{{ $lookup }}" @endif @required($required ?? false)>
            @if (!($required ?? false))
                <option value="">— Pilih —</option>
                @endif @foreach ($options ?? [] as $key => $option)
                    <option value="{{ $key }}" @selected((string) old($name, $value ?? '') === (string) $key)>{{ $option }}</option>
                @endforeach
        </select>
    @elseif(($kind ?? 'text') === 'boolean')
        <select id="{{ $inputId }}" name="{{ $name }}">
            <option value="1" @selected(old($name, $value ?? true))>Ya</option>
            <option value="0" @selected(!old($name, $value ?? true))>Tidak</option>
        </select>
    @else<input id="{{ $inputId }}" name="{{ $name }}" type="{{ $kind ?? 'text' }}"
            value="{{ ($kind ?? 'text') === 'password' ? '' : old($name, $value ?? '') }}" @required($required ?? false)
            @if (($kind ?? 'text') === 'number') step="{{ $step ?? '1' }}" @endif
            @if (($kind ?? 'text') === 'password') autocomplete="new-password" @endif>
    @endif
    @error($name)
        <p class="helper text-danger">{{ $message }}</p>
    @enderror
</div>

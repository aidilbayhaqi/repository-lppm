@if(session('success'))
    <div role="status" class="notice notice-success notice-dismissible">
        <span>{{ session('success') }}</span>
        <button type="button" class="icon-button" data-dismiss-notice aria-label="Tutup pemberitahuan">×</button>
    </div>
@endif
@if(session('warning'))
    <div
        role="alert"
        class="notice notice-error notice-dismissible"
    >
        <div>
            <strong>Tindakan belum berhasil</strong>
            <p>{{ session('warning') }}</p>
        </div>

        <button
            type="button"
            class="icon-button"
            data-dismiss-notice
            aria-label="Tutup pemberitahuan"
        >
            ×
        </button>
    </div>
@endif
@php
    // Error data in a child Blade view must not override Laravel's validation bag.
    // Prefer Laravel's shared validation bag, but recover flashed errors
    // when a child template supplied an empty or invalid $errors value.
    $feedbackErrors = ($errors ?? null) instanceof \Illuminate\Support\ViewErrorBag
        ? $errors
        : null;
    if (! $feedbackErrors?->any()) {
        $feedbackErrors = session('errors');
    }
@endphp
@if($feedbackErrors instanceof \Illuminate\Support\ViewErrorBag && $feedbackErrors->any())
    <div id="form-errors" role="alert" tabindex="-1" class="notice notice-error" data-error-summary>
        <strong>Perubahan belum disimpan</strong>
        <p>Periksa bagian yang ditandai, lalu coba simpan kembali.</p>
        <ul>
            @foreach($feedbackErrors->messages() as $field => $messages)
                <li id="error-summary-{{ $loop->index }}" data-error-field="{{ $field }}">{{ $messages[0] }}</li>
            @endforeach
        </ul>
    </div>
@endif

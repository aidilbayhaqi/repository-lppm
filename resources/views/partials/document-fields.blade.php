<div data-document-fields>
<h3 data-document-heading>{{ \App\Support\DocumentTypes::label($document->jenis_dokumen ?? 'dokumen_utama') }}</h3>

@php
    $fileInputId = 'document-file-' . ($document->id ?? 'new');
@endphp

<input
    type="hidden"
    name="lock_version"
    value="{{ $work->lock_version }}"
>

<label for="{{ $fileInputId }}">
    File dokumen PDF

    <input
        id="{{ $fileInputId }}"
        type="file"
        name="file"
        accept=".pdf,application/pdf"
        required
        aria-describedby="{{ $fileInputId }}-help"
    >
</label>

<p
    id="{{ $fileInputId }}-help"
    class="helper"
>
    Format PDF, maksimal 20 MB.
    Pastikan file dapat dibuka dengan PDF reader.
</p>

@error('file')
    <p class="text-danger" role="alert">
        {{ $message }}
    </p>
@enderror

<label>
    Jenis dokumen

    <select name="jenis_dokumen" data-document-kind required>
        @foreach ([
            'dokumen_utama' => 'Dokumen utama',
            'cover' => 'Cover',
            'sertifikat' => 'Sertifikat',
            'lampiran' => 'Lampiran',
            'pendukung' => 'Pendukung',
            'lainnya' => 'Lainnya',
        ] as $key => $label)
            <option
                value="{{ $key }}"
                @selected(
                    ($document->jenis_dokumen ?? 'dokumen_utama') === $key
                )
            >
                {{ $label }}
            </option>
        @endforeach
    </select>
</label>

<label>
    Akses dokumen

    <select name="publik" required>
        <option
            value="0"
            @selected(!($document->publik ?? false))
        >
            Privat
        </option>

        <option
            value="1"
            @selected($document->publik ?? false)
        >
            Publik setelah karya diterbitkan
        </option>
    </select>
</label>

@error('lock_version')
    <p class="text-danger" role="alert">
        {{ $message }}
    </p>
@enderror

</div>

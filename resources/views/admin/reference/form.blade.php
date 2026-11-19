@extends('layouts.repository')
@section('title', ($record->exists ? 'Ubah ' : 'Tambah ') . $definition['label'])
@section('content')<div class="page-heading">
        <div><a class="breadcrumb" href="{{ route('admin.reference.index', $entity) }}">← Kembali</a>
            <h1>{{ $record->exists ? 'Ubah' : 'Tambah' }} {{ strtolower($definition['label']) }}</h1>
        </div>
    </div>
    <form method="post"
        action="{{ $record->exists ? route('admin.reference.update', [$entity, $record->id]) : route('admin.reference.store', $entity) }}">
        @csrf <input type="hidden" name="lock_version" value="{{ $record->lock_version ?? 0 }}">
        @if ($record->exists)
            @method('PUT')
        @endif
        <section class="panel">
            <div class="form-grid">
                @foreach ($definition['fields'] as $name => $field)
                    @include('partials.field', [
                        'name' => $name,
                        'label' => $field[0],
                        'kind' => $field[1],
                        'required' => str_starts_with($field[2], 'required'),
                        'value' =>
                            $record->$name ??
                            ($field[1] === 'boolean' ? true : ($name === 'negara' ? 'Indonesia' : '')),
                        'options' => \App\Support\ReferenceCatalog::options($field, old($name, $record->$name)),
                        'lookup' => isset($field['ref'])
                            ? route('lookups', \App\Support\LookupOptions::entity($field['ref'][0]))
                            : null,
                        'wide' => $field[1] === 'textarea',
                    ])
                @endforeach
            </div>
            @if ($entity === 'penulis')
                <p class="helper">Relasi dosen wajib untuk jenis dosen. Akun login dihubungkan oleh admin melalui menu
                    Pengguna & akses.</p>
            @endif
        </section>
        <div class="form-actions"><a href="{{ route('admin.reference.index', $entity) }}">Batal</a><button
                class="button">Simpan data</button></div>
</form>@endsection

@extends('layouts.repository')
@section('title', ($record->exists ? 'Ubah' : 'Tambah') . ' akun')
@section('content')<div class="page-heading">
        <div><a class="breadcrumb" href="{{ route('admin.users.index') }}">← Pengguna & akses</a>
            <h1>{{ $record->exists ? 'Ubah' : 'Tambah' }} akun</h1>
        </div>
    </div>
    <form method="post" action="{{ $record->exists ? route('admin.users.update', $record) : route('admin.users.store') }}">
        @csrf <input type="hidden" name="lock_version" value="{{ $record->lock_version ?? 0 }}">
        @if ($record->exists)
            @method('PUT')
        @endif
        <section class="panel">
            <div class="form-grid">@include('partials.field', [
                'name' => 'nama',
                'label' => 'Nama lengkap',
                'value' => $record->nama,
                'required' => true,
            ]) @include('partials.field', [
                'name' => 'email',
                'label' => 'Email',
                'kind' => 'email',
                'value' => $record->email,
                'required' => true,
            ]) @include('partials.field', [
                'name' => 'role',
                'label' => 'Peran',
                'kind' => 'select',
                'value' => $record->role ?? 'mahasiswa',
                'required' => true,
                'options' => [
                    'admin' => 'Admin LPPM',
                    'operator' => 'Operator',
                    'verifier' => 'Verifier',
                    'dosen' => 'Dosen',
                    'mahasiswa' => 'Mahasiswa',
                    'peneliti_eksternal' => 'Peneliti eksternal',
                ],
            ])
                @include('partials.field', [
                    'name' => 'is_active',
                    'label' => 'Akun aktif',
                    'kind' => 'boolean',
                    'value' => $record->is_active ?? true,
                ]) @include('partials.field', [
                    'name' => 'password',
                    'label' => $record->exists
                        ? 'Password baru (kosongkan jika tetap)'
                        : 'Password (minimal 12 karakter)',
                    'kind' => 'password',
                    'required' => !$record->exists,
                ]) @include('partials.field', [
                    'name' => 'password_confirmation',
                    'label' => 'Konfirmasi password',
                    'kind' => 'password',
                    'required' => !$record->exists,
                ])
                @include('partials.field', [
                    'lookup' => route('lookups', ['entity' => 'penulis', 'available_for' => $record->id ?? 0]),
                    'name' => 'penulis_id',
                    'label' => 'Hubungkan profil penulis (opsional)',
                    'kind' => 'select',
                    'value' => $record->penulis?->id,
                    'options' => $authors->mapWithKeys(fn($a) => [$a->id => $a->nama . ' · #' . $a->id]),
                    'wide' => true,
                ])</div>
            <p class="helper">Akun tidak otomatis menjadi penulis. Profil dapat dihubungkan jika identitasnya sudah
                diverifikasi oleh pengelola.</p>
        </section>
        <div class="form-actions"><button class="button">Simpan akun</button></div>
</form>
@endsection

@extends('layouts.repository')
@section('title', 'Pengguna & akses')
@section('content')<div class="page-heading">
        <div>
            <div class="eyebrow">ADMINISTRASI</div>
            <h1>Pengguna & akses</h1>
            <p>Kelola peran, status akun, dan hubungan ke penulis.</p>
        </div><a class="button" href="{{ route('admin.users.create') }}">+ Tambah akun</a>
    </div>
    <section class="panel">
        <div class="table-wrap" role="region" aria-label="Tabel data, dapat digeser horizontal" tabindex="0">
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Peran</th>
                        <th>Status</th>
                        <th><span class="sr-only">Tindakan</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $record)
                        <tr>
                            <td class="table-title">{{ $record->nama }}</td>
                            <td>{{ $record->email }}</td>
                            <td>{{ str_replace('_', ' ', $record->role) }}</td>
                            <td>{{ $record->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                            <td><a class="arrow-link" href="{{ route('admin.users.edit', $record) }}">Ubah ↗</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="table-hint">Geser tabel ke samping untuk melihat seluruh kolom.</p>
        <div class="pagination-wrap">{{ $users->links() }}</div>
</section>@endsection

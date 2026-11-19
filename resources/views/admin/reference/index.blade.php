@extends('layouts.repository')
@section('title', $definition['label'])
@section('content')<div class="page-heading">
        <div>
            <div class="eyebrow">MASTER DATA</div>
            <h1>{{ $definition['label'] }}</h1>
            <p>{{ $records->total() }} data terdaftar</p>
        </div><a class="button" href="{{ route('admin.reference.create', $entity) }}">+ Tambah data</a>
    </div>
    <section class="panel">
        <form class="toolbar" method="get"><input aria-label="Cari data" name="q"
                placeholder="Cari {{ strtolower($definition['label']) }}..." value="{{ request('q') }}"><button
                class="button button-small">Cari</button></form>
        <div class="table-wrap" role="region" aria-label="Tabel data, dapat digeser horizontal" tabindex="0">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ $definition['label'] }}</th>
                        <th>Kode / jenis</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        <tr>
                            <td>{{ $record->id }}</td>
                            <td class="table-title">{{ $record->{$definition['title']} }}</td>
                            <td>{{ $record->kode ?? ($record->jenis_penulis ?? ($record->kategori ?? '—')) }}</td>
                            <td>{{ $record->aktif === false ? 'Nonaktif' : 'Aktif' }}</td>
                            <td>
                                <div class="toolbar" style="margin:0"><a class="arrow-link"
                                        href="{{ route('admin.reference.edit', [$entity, $record->id]) }}">Ubah</a>
                                    <form method="post"
                                        action="{{ route('admin.reference.destroy', [$entity, $record->id]) }}"
                                        data-confirm="Hapus data ini? Data yang masih digunakan dapat memengaruhi relasi.">
                                        @csrf @method('DELETE')<input type="hidden" name="lock_version"
                                            value="{{ $record->lock_version }}"><button
                                            class="text-button text-danger">Hapus</button></form>
                                </div>
                            </td>
                    </tr>@empty<tr>
                            <td colspan="5" class="empty-cell">Belum ada data. Tambahkan data referensi pertama.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="table-hint">Geser tabel ke samping untuk melihat seluruh kolom.</p>
        <div class="pagination-wrap">{{ $records->links() }}</div>
</section>
@endsection

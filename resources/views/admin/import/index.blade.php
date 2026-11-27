@extends('layouts.repository')
@section('title', 'Import data')
@section('content')<div class="page-heading">
        <div>
            <h1>Import karya</h1>
            <p>Unggah CSV atau XLSX, periksa hasilnya, lalu simpan sebagai draf.</p>
        </div>
    </div>
    <div class="import-steps">
        <div><b>SIAPKAN</b>
            <h2>Gunakan template</h2>
            <p>Isi nama penulis baru beserta jenis dan NIDN untuk dosen, atau gunakan ID penulis yang tersedia.</p>
        </div>
        <div><b>PERIKSA</b>
            <h2>Preview & validasi</h2>
            <p>Periksa metadata, duplikat, dan relasi. Semua error harus diperbaiki sebelum disimpan.</p>
        </div>
        <div><b>SIMPAN</b>
            <h2>Simpan semua baris</h2>
            <p>Data masuk sebagai draf dan tetap mengikuti proses verifikasi/penerbitan.</p>
        </div>
    </div>
    <section class="panel">
        <div class="panel-heading">
            <h2>Unggah file</h2>
        </div>
        <div class="chip-row">
            @foreach (\App\Support\WorkType::TYPES as $key => $label)
                <a class="chip" href="{{ route('admin.import.template', $key) }}">↓ Excel · {{ $label }}</a>
            @endforeach
        </div>
        <form class="compact-form" style="max-width:650px" action="{{ route('admin.import.preview') }}" method="post"
            enctype="multipart/form-data">@csrf<label>Jenis karya<select name="type">
                    @foreach (\App\Support\WorkType::TYPES as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label><label>File CSV / XLSX (maksimal 5 MB, 1.000 baris)<input type="file" name="file"
                    accept=".csv,.xlsx" required></label><button class="button">Periksa & tampilkan preview ↗</button>
        </form>
        <details class="advanced-filters"><summary>Format file dan kolom penulis</summary><p class="helper">CSV UTF-8 (koma atau titik koma). Untuk XLSX, gunakan sheet pertama tanpa rumus. Tanggal: YYYY-MM-DD; biaya: desimal titik. Preview berlaku 24 jam.</p><p class="helper">Beberapa penulis dipisahkan tanda | pada kolom penulis_nama, penulis_jenis, dan penulis_nidn. Contoh: Rani|Budi, mahasiswa|dosen, |0011223344. Gunakan nama baru atau penulis_ids dalam satu baris, bukan keduanya.</p></details>
    </section>
    <section class="panel">
        <h2>Riwayat import Anda</h2>
        <div class="table-wrap" role="region" aria-label="Tabel data, dapat digeser horizontal" tabindex="0">
            <table>
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Jenis</th>
                        <th>Status</th>
                        <th>Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($batches as $batch)
                        <tr>
                            <td><a class="arrow-link" href="{{ route('admin.import.show', $batch) }}">{{ $batch->filename }}
                                    ↗</a></td>
                            <td>{{ \App\Support\WorkType::TYPES[$batch->type] }}</td>
                            <td>{{ $batch->status === 'committed' ? 'Tersimpan' : 'Menunggu pemeriksaan' }}</td>
                            <td>{{ $batch->created_at->format('d M Y H:i') }}</td>
                    </tr>@empty<tr>
                            <td colspan="4" class="empty-cell">Belum ada import.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="table-hint">Geser tabel ke samping untuk melihat seluruh kolom.</p>{{ $batches->links() }}
</section>@endsection

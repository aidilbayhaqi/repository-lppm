@extends('layouts.repository')
@section('title', 'Preview import')
@section('content')<a class="breadcrumb" href="{{ route('admin.import.index') }}">
        ← Kembali ke import</a>
    <div class="page-heading">
        <div>
            <h1>Preview import</h1>
            <p>{{ $batch->filename }} · {{ $batch->type }} · {{ $batch->created_at->format('d M Y H:i') }}</p>
        </div>
    </div>
    <div class="dashboard-stats">
        <div><span>Baris valid</span><strong>{{ count($batch->rows) }}</strong></div>
        <div><span>Baris bermasalah</span><strong>{{ count($batch->errors) }}</strong></div>
        <div><span>Status</span><strong
                style="font-size:23px">{{ $batch->status === 'committed' ? 'Tersimpan' : 'Preview' }}</strong></div>
    </div>
    @if ($batch->errors)
        <section class="panel">
            <h2>Perbaiki baris berikut</h2>
            <div class="table-wrap" role="region" aria-label="Tabel data, dapat digeser horizontal" tabindex="0">
                <table>
                    <thead>
                        <tr>
                            <th>Baris</th>
                            <th>Masalah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($batch->errors as $line => $rowErrors)
                            <tr>
                                <td>{{ $line }}</td>
                                <td>
                                    @foreach ($rowErrors as $error)
                                        <p>{{ $error }}</p>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
        </section>
    @endif
    <section class="panel">
        <h2>Data siap disimpan</h2>
        <div class="table-wrap" role="region" aria-label="Tabel data, dapat digeser horizontal" tabindex="0">
            <table>
                <thead>
                    <tr>
                        <th>Baris</th>
                        <th>Judul</th>
                        <th>Tahun</th>
                        <th>Penulis</th>
                        <th>Akses</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($batch->rows as $row)
                        <tr>
                            <td>{{ $row['line'] }}</td>
                            <td>{{ $row['data']['judul'] }}</td>
                            <td>{{ $row['data'][\App\Support\WorkType::year($batch->type)] ?? '—' }}</td>
                            <td>{{ collect($row['data']['authors'])->map(fn($a) => $a['nama'] ?? ($authorNames[$a['id']] ?? 'Referensi tidak tersedia'))->join(', ') }}</td>
                            <td>{{ ['public'=>'Publik setelah terbit','private'=>'Privat tim karya','internal'=>'Internal tim karya','restricted'=>'Terbatas tim karya'][$row['data']['public_visibility']] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
    </section>
    @if ($batch->status !== 'committed')
        <form method="post" action="{{ route('admin.import.commit', $batch) }}">@csrf<div class="form-actions"><a
                    href="{{ route('admin.import.index') }}">Unggah ulang</a><button class="button"
                    @disabled(count($batch->errors) > 0 || count($batch->rows) === 0 || $batch->created_at->lt(now()->subDay()))>Simpan {{ count($batch->rows) }} karya sebagai draf</button></div>
        </form>
    @endif @endsection

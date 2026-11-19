@extends('layouts.repository')
@section('title', 'Ringkasan')
@section('content')
    <div class="page-heading">
        <div><h1>Ringkasan repositori</h1><p>Pantau karya, pemeriksaan, dan penerbitan.</p></div>
        @can('create', \App\Models\Publikasi::class)<a class="button" href="{{ route('admin.works.create', 'publikasi') }}"><x-icon name="plus" size="18" />Tambah jurnal</a>@endcan
    </div>
    <div class="dashboard-stats">
        @foreach($stats as $label => $number)
            <div><span>{{ $label }}</span><strong>{{ number_format($number, 0, ',', '.') }}</strong></div>
        @endforeach
    </div>
    @include('partials.dashboard-charts')
    <div class="dashboard-grid">
        <section class="panel activity-panel">
            <div class="panel-heading"><div><h2>Aktivitas jurnal</h2><p>Perubahan terbaru pada karya Anda.</p></div><a class="arrow-link" href="{{ route('admin.works.index', 'publikasi') }}">Lihat semua<x-icon name="arrow-right" size="16" /></a></div>
            <div class="table-wrap"><table class="activity-table"><thead><tr><th>Karya</th><th>Status</th><th>Diperbarui</th></tr></thead><tbody>
                @forelse($recent as $work)
                    <tr>
                        <td class="activity-work"><a class="table-title" href="{{ route('admin.works.show', ['publikasi', $work->id]) }}">{{ $work->judul }}</a><small>{{ $work->penulis->pluck('nama')->join(', ') }}</small></td>
                        <td class="activity-status"><x-status :value="$work->repository_status" /></td>
                        <td class="activity-date nowrap">{{ $work->updated_at->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty-cell">Belum ada jurnal. Tambahkan karya pertama Anda.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>
        <section class="panel repository-summary">
            <h2>Status repositori</h2>
            <div class="status-list">
                @forelse($counts as $status => $count)
                    <a href="{{ route('admin.works.index', ['type' => 'publikasi', 'status' => $status]) }}"><x-status :value="$status" /><strong>{{ $count }}</strong></a>
                @empty
                    <p class="muted">Belum ada status untuk ditampilkan.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection

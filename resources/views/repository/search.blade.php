@extends('layouts.repository')
@section('title', 'Jelajahi karya')
@section('content')
    <section class="page-intro container"><div class="eyebrow">Koleksi akademik</div><h1>Jelajahi karya</h1><p>Temukan jurnal, buku, dan inovasi dari komunitas UMIBA.</p></section>
    <div class="container search-layout section-bottom">
        <aside class="search-filters" aria-label="Filter pencarian">
            <details class="filter-panel" open data-filter-panel>
                <summary><x-icon name="filter" size="19" /><span>Filter pencarian</span><x-icon name="chevron-down" size="17" class="filter-chevron" /></summary>
                <form method="get" action="{{ route('repository.search') }}">
                    <div class="filter-field"><label for="q">Kata kunci</label><input id="q" name="q" value="{{ request('q') }}" maxlength="150" placeholder="Judul, penulis, DOI…"></div>
                    <div class="filter-field"><label for="type">Jenis karya</label><select id="type" name="type"><option value="">Semua koleksi</option>@foreach(\App\Support\WorkType::TYPES as $key => $label)<option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>@endforeach</select></div>
                    @include('partials.discovery-filters')
                    <div class="filter-actions"><button class="button full" type="submit">Terapkan filter</button><a class="reset-link" href="{{ route('repository.search') }}">Reset pencarian</a></div>
                </form>
            </details>
        </aside>
        <div class="search-results">
            <div class="results-heading"><strong>{{ number_format($results->total(), 0, ',', '.') }} karya ditemukan</strong><span class="muted">{{ ['newest'=>'Tahun terbaru','oldest'=>'Tahun terlama','title'=>'Judul A–Z'][request('sort','newest')] ?? 'Tahun terbaru' }}</span></div>
            @if(collect(request()->only(['q', 'type', 'year', 'fakultas_id', 'program_studi_id', 'dosen_id', 'pengindeks_id', 'sinta_rank', 'scopus_quartile', 'scopus_indexed', 'publication_type']))->filter(fn ($value) => filled($value))->isNotEmpty())
                <p class="search-context">Hasil sesuai filter{{ request('q') ? ' untuk “'.request('q').'”' : ' yang dipilih' }}. <a href="{{ route('repository.search') }}">Hapus filter</a></p>
            @endif
            <div class="results-list results-list--cards">
                @forelse($results as $item)
                    @include('partials.work-card')
                @empty
                    <div class="empty-state"><x-icon name="search" size="32" /><h2>Belum ada hasil yang cocok</h2><p>Coba kata kunci lain atau kurangi filter pencarian.</p><a href="{{ route('repository.search') }}" class="arrow-link">Lihat seluruh koleksi<x-icon name="arrow-right" size="18" /></a></div>
                @endforelse
            </div>
            <div class="pagination-wrap">{{ $results->links() }}</div>
        </div>
    </div>
@endsection

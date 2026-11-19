@extends('layouts.repository')
@section('title', \App\Support\WorkType::TYPES[$type])
@section('content')<div class="page-heading">
        <div>
            <h1>{{ \App\Support\WorkType::TYPES[$type] }}</h1>
            <p>{{ $records->total() }} karya sesuai hak akses Anda.</p>
        </div>
        @can('create', \App\Support\WorkType::model($type))
            <a class="button" href="{{ route('admin.works.create', $type) }}">Tambah {{ strtolower(\App\Support\WorkType::TYPES[$type]) }}</a>
        @endcan
    </div>
    <section class="panel">
        <form class="work-filters" method="get">
            <div class="search-toolbar">
                <div class="filter-field"><label for="q">Cari karya</label><input id="q" name="q" maxlength="150" value="{{ request('q') }}" placeholder="Judul, penulis, DOI, atau nama jurnal"></div>
                <div class="filter-field"><label for="status">Status repositori</label><select id="status" name="status"><option value="">Semua status</option>@foreach(\App\Enums\RepositoryStatus::cases() as $state) @if($type === 'publikasi' || in_array($state->value,['draft','published','archived']))<option value="{{ $state->value }}" @selected(request('status') === $state->value)>{{ $state->label() }}</option>@endif @endforeach</select></div>
                <button class="button" type="submit">Terapkan</button>
                <a class="reset-link" href="{{ route('admin.works.index',$type) }}">Reset</a>
            </div>
            @php($activeFilters = collect(request()->except(['q','status','page','sort']))->filter(fn($value)=>filled($value))->count())
            <details class="advanced-filters" @if($activeFilters) open @endif><summary><x-icon name="filter" size="17" /> Filter lanjutan @if($activeFilters)<span>{{ $activeFilters }} aktif</span>@endif</summary>
                @include('partials.discovery-filters', ['adminFilters'=>true])
                <p class="helper">Filter SINTA dan Scopus dapat digabungkan. Prodi dan fakultas mengikuti afiliasi dosen pada daftar penulis.</p>
                <button class="button button-small" type="submit">Terapkan filter</button>
            </details>
        </form>
        <div class="table-wrap">
            <table class="work-list-table">
                <thead>
                    <tr>
                        <th>Judul & penulis</th>
                        <th>Tahun</th>
                        <th>Status</th>
                        <th>Akses</th>
                        <th><span class="sr-only">Tindakan</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $work)
                        <tr>
                            <td class="work-list-title"><a class="table-title"
                                    href="{{ route('admin.works.show', [$type, $work->id]) }}">{{ $work->judul }}</a><small>{{ $work->{\App\Support\WorkType::authors($work)}->pluck('nama')->join(' · ') }}</small>
                                @if($type === 'publikasi') @include('partials.journal-tags') @endif
                            </td>
                            <td class="work-list-year">{{ $work->{\App\Support\WorkType::year($type)} ?? '—' }}</td>
                            <td class="work-list-status"><x-status :value="\App\Support\WorkType::status($work)" /></td>
                            <td class="work-list-access">{{ ['public'=>'Publik','internal'=>'Internal','private'=>'Privat','restricted'=>'Terbatas'][$work->public_visibility] ?? 'Belum diatur' }}</td>
                            <td class="work-list-action"><a class="arrow-link"
                                    href="{{ route('admin.works.show', [$type, $work->id]) }}">Lihat detail</a></td>
                    </tr>@empty<tr>
                            <td colspan="5" class="empty-cell">Belum ada karya yang cocok. Tambah karya atau ubah
                                pencarian.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $records->links() }}</div>
</section>@endsection

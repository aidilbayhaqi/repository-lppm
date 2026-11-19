@php($cardType = \App\Support\WorkType::key($item))
@php($cardUrl = route(($internalCard ?? false) ? 'admin.works.show' : 'repository.show', [$cardType, $item->id]))
<article class="work-card work-card--catalog" data-type="{{ $cardType }}">
    <a href="{{ $cardUrl }}" class="work-card-cover" aria-label="Lihat sampul dan detail {{ $item->judul }}">
        <img src="{{ $item->cover ? route('works.cover.show', [$cardType, $item->id]) : asset('images/cover-placeholder.svg') }}"
             alt="{{ $item->cover ? 'Sampul ' . $item->judul : '' }}"
             width="240" height="320" loading="lazy" decoding="async" class="work-card-cover-image">
    </a>
    <div class="work-card-content">
        <div class="work-card-top">
            <span class="work-type"><x-icon :name="$cardType === 'publikasi' ? 'document' : ($cardType === 'buku' ? 'book' : 'shield')" size="15" />{{ $cardType === 'hki' ? 'HKI' : \App\Support\WorkType::TYPES[$cardType] }}</span>
            <span>{{ $item->{\App\Support\WorkType::year($cardType)} ?? 'Tanpa tahun' }}</span>
        </div>
        <h3><a href="{{ $cardUrl }}">{{ $item->judul }}</a></h3>
        <p class="authors">{{ $item->{\App\Support\WorkType::authors($item)}->pluck('nama')->join(' · ') ?: 'Penulis belum diisi' }}</p>
        @if($cardType === 'publikasi') @include('partials.journal-tags', ['work'=>$item]) @endif
        <div class="work-card-bottom">
            <span>{{ $cardType === 'publikasi' ? ($item->journal_name ?? 'Jurnal akademik') : ($cardType === 'buku' ? ($item->penerbit?->nama ?? 'Buku akademik') : $item->jenis) }}</span>
            <a aria-label="Lihat detail {{ $item->judul }}" href="{{ $cardUrl }}"><x-icon name="arrow-up-right" size="19" /></a>
        </div>
    </div>
</article>

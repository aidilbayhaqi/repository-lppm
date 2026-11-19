<div class="journal-tags" aria-label="Pengindeksan jurnal">
    @if ($work->sinta_rank)
        <span class="journal-tag">SINTA {{ $work->sinta_rank }}</span>
    @endif
    @if ($work->scopus_indexed)
        <span
            class="journal-tag">Scopus{{ $work->scopus_quartile ? ' ' . $work->scopus_quartile : ' · kuartil belum diisi' }}</span>
    @endif
</div>

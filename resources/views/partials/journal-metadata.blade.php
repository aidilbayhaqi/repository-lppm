<div class="journal-information">
    @include('partials.journal-tags')
    <dl>
        <div><dt>Jenis karya</dt><dd>{{ \App\Support\JournalMetadata::TYPES[$work->publication_type] ?? 'Belum diisi' }}</dd></div>
        <div><dt>Nama jurnal / prosiding</dt><dd>{{ $work->journal_name ?? 'Belum diisi' }}</dd></div>
        <div><dt>SINTA</dt><dd>{{ $work->sinta_rank ? 'SINTA '.$work->sinta_rank : 'Belum dicatat' }}</dd></div>
        <div><dt>Scopus</dt><dd>{{ $work->scopus_indexed ? ($work->scopus_quartile ?? 'Terindeks, kuartil belum dicatat') : 'Belum dicatat' }}</dd></div>
        @if($work->indexing_year)<div><dt>Tahun data indeks</dt><dd>{{ $work->indexing_year }}</dd></div>@endif
        @foreach(['isbn'=>'ISBN prosiding','issn_cetak'=>'ISSN cetak','issn_elektronik'=>'ISSN elektronik'] as $field=>$label)
            @if($work->$field)<div><dt>{{ $label }}</dt><dd>{{ $work->$field }}</dd></div>@endif
        @endforeach
    </dl>
    @if($work->indexing_source && preg_match('~^https?://~i', $work->indexing_source))<a class="arrow-link" href="{{ $work->indexing_source }}" target="_blank" rel="noopener noreferrer">Lihat sumber pengindeksan <x-icon name="arrow-up-right" size="16" /></a>@endif
    <p class="helper">Informasi indeks mengikuti data yang dicatat pada karya ini.</p>
</div>

<dl>
    <div><dt>Fakultas asal jurnal</dt><dd>{{ $work->fakultas?->nama_fakultas ?? 'Belum diisi' }}</dd></div>
    <div><dt>Program studi asal jurnal</dt><dd>{{ $work->programStudi?->nama ?? 'Belum diisi' }}</dd></div>
</dl>

@if($paginator->hasPages())
<nav class="history-nav" aria-label="Halaman riwayat">
    @if($paginator->onFirstPage())<span aria-disabled="true">Sebelumnya</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">Sebelumnya</a>@endif
    <span>{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
    @if($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya</a>@else<span aria-disabled="true">Berikutnya</span>@endif
</nav>
@endif

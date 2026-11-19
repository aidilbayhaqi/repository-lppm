<div class="activity-feed">
    @forelse($histories as $history)
        @php($changes = \App\Support\AuditHistoryPresenter::changes($history))
        <article class="activity-entry">
            <span class="activity-dot" aria-hidden="true"></span>
            <div class="activity-body">
                <div class="activity-heading"><h3>{{ \App\Support\AuditHistoryPresenter::title($history->action) }}</h3><time datetime="{{ $history->created_at->toIso8601String() }}">{{ $history->created_at->format('d M Y, H:i') }}</time></div>
                <p>{{ \App\Support\AuditHistoryPresenter::summary($history->action) }}</p>
                <span class="activity-by">{{ $history->user?->nama ?? 'Sistem' }}</span>
                @if($history->description)<blockquote>{{ $history->description }}</blockquote>@endif
                @if($changes)
                    <details class="activity-changes"><summary>Detail perubahan ({{ count($changes) }})</summary>
                        <ul>@foreach($changes as $change)
                            <li>@if($change['list']){{ $change['label'] }} diperbarui, dari {{ $change['before'] }} menjadi {{ $change['after'] }}.@else{{ $change['label'] }} berubah dari <strong>{{ $change['before'] }}</strong> menjadi <strong>{{ $change['after'] }}</strong>.@endif</li>
                        @endforeach</ul>
                    </details>
                @endif
            </div>
        </article>
    @empty<p class="helper">Belum ada aktivitas pada karya ini.</p>@endforelse
</div>
<div class="history-pagination"><span>{{ $histories->firstItem() ?? 0 }}–{{ $histories->lastItem() ?? 0 }} dari {{ $histories->total() }} aktivitas</span>{{ $histories->links('partials.history-pagination') }}</div>

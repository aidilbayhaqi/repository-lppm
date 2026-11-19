@php($progress = \App\Support\WorkflowPresentation::describe($work->archived_at ? 'archived' : $work->repository_status))
<div class="workflow-overview">
    <ol class="workflow-steps" aria-label="Tahap penerbitan">
        @foreach (['Draf', 'Verifikasi', 'Disetujui', 'Penerbitan', 'Terbit'] as $step)
            <li @class([
                'is-complete' => $loop->index < $progress['step'],
                'is-current' => $loop->index === $progress['step'],
            ]) @if ($loop->index === $progress['step']) aria-current="step" @endif>
                <span>{{ $loop->iteration }}</span>{{ $step }}</li>
        @endforeach
    </ol>
    <h3>{{ $progress['title'] }}</h3>
    <p>{{ $progress['detail'] }}</p>
</div>

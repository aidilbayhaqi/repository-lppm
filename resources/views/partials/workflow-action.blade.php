@php
    $needsNotes = in_array($action, ['revision', 'reject', 'reopen', 'unpublish', 'archive'])
        || (in_array($action, ['review', 'approve']) && auth()->user()->isAdmin() && app(\App\Policies\WorkPolicy::class)->verificationConflict(auth()->user(), $work));
    $messages = [
        'submit' => 'Metadata akan dikunci selama pemeriksaan. Pastikan penulis dan tahun sudah benar.',
        'review' => 'Mulai pemeriksaan pengajuan ini?',
        'approve' => 'Pengajuan akan ditandai terverifikasi. Penerbitan tetap dilakukan oleh admin atau operator.',
        'revision' => 'Catatan revisi akan disimpan. Penulis dapat memperbaiki dan mengajukan kembali.',
        'reject' => 'Pengajuan akan ditolak dengan alasan yang Anda tulis.',
        'publish' => 'Karya akan langsung diterbitkan setelah pemeriksaan akhir berhasil. Lanjutkan penerbitan?',
        'reopen' => 'Karya akan dibuka untuk revisi dan harus diperiksa kembali.',
        'unpublish' => 'Karya tidak lagi tampil sebagai terbit. Riwayat tetap tersimpan.',
        'archive' => 'Karya akan disembunyikan dari repositori. Admin atau operator dapat memulihkannya.',
        'restore' => 'Karya dikembalikan sebagai draf dan belum dapat dilihat publik.',
    ];
@endphp

<form class="workflow-form" data-requires-notes="{{ $needsNotes ? 'true' : 'false' }}" method="post" action="{{ route('admin.works.workflow', [$type, $work->id]) }}"
    data-confirm="{{ $messages[$action] }}" data-confirm-title="{{ $label }}?"
    data-confirm-label="{{ $label }}"
    data-danger="{{ in_array($action, ['reject', 'archive', 'unpublish']) ? 'true' : 'false' }}">
    @csrf
    <input type="hidden" name="action" value="{{ $action }}">
    <input type="hidden" name="lock_version" value="{{ $work->lock_version }}">
    @if ($needsNotes)
        <div data-action-notes><label for="notes-{{ $action }}">Alasan {{ strtolower($label) }}</label>
        <textarea id="notes-{{ $action }}" name="notes" placeholder="Jelaskan bagian yang perlu ditindaklanjuti."
            required maxlength="4000">{{ old('action') === $action ? old('notes') : '' }}</textarea></div>
    @endif
    <button class="button {{ ($secondary ?? false) ? 'button-secondary' : '' }}"
        type="submit">{{ $label }}</button>
</form>

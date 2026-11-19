
@extends('layouts.repository')

@section('title', 'Detail ' . \App\Support\WorkType::TYPES[$type])

@section('content')
    @php
        $authorRelation = \App\Support\WorkType::authors($work);
        $yearField = \App\Support\WorkType::year($type);
    @endphp

    {{-- Breadcrumb --}}
    <a
        class="breadcrumb"
        href="{{ route('admin.works.index', $type) }}"
    >
        ← Kembali ke {{ strtolower(\App\Support\WorkType::TYPES[$type]) }}
    </a>

    {{-- Heading --}}
    <div class="page-heading">
        <div class="work-heading">
            <x-status
                :value="\App\Support\WorkType::status($work)"
            />

            <h1>{{ $work->judul }}</h1>

            <p>
                {{ $work->{$authorRelation}->pluck('nama')->join(' · ') ?: 'Penulis belum tersedia' }}
                ·
                {{ $work->{$yearField} ?? 'Tahun belum diisi' }}
            </p>
        </div>

        @can('update', $work)
            <a
                class="button button-secondary"
                href="{{ route('admin.works.edit', [$type, $work->id]) }}"
            >
                Ubah metadata
            </a>
        @endcan
    </div>

    {{-- Public information --}}
    @if (\App\Support\WorkType::public($work))
        <p class="info-banner">
            Karya tersedia untuk publik.

            <a
                class="arrow-link"
                href="{{ route('repository.show', [$type, $work->id]) }}"
            >
                Buka halaman publik ↗
            </a>
        </p>
    @endif

    @if($type === 'publikasi')
        @include('partials.workflow-progress')
    @endif

    {{-- Publication workflow --}}
    <section class="panel">
        <div class="panel-heading">
            <h2>Proses karya</h2>
        </div>

        <p class="helper">
            @if ($type === 'publikasi')
                Pengajuan → Pemeriksaan → Terverifikasi → Penerbitan.
                Revisi harus diajukan dan diperiksa ulang.
            @else
                Karya diterbitkan oleh Admin atau Operator
                setelah metadata lengkap.
            @endif
        </p>

        {{-- Readiness checklist --}}
        @if (
            $type === 'publikasi' &&
            !in_array(
                $work->repository_status,
                ['publishing', 'published'],
                true
            )
        )
            @include('partials.publication-readiness', [
                'readiness' => $readiness,
            ])
        @endif

        @if(auth()->user()->isAdmin() && in_array($work->repository_status, ['submitted','pending_verification']) && app(\App\Policies\WorkPolicy::class)->verificationConflict(auth()->user(), $work))
            <p class="helper">Anda dapat memeriksa karya ini sebagai admin. Catatan pemeriksaan wajib diisi karena Anda tercatat sebagai pembuat atau penulis.</p>
        @endif
        {{-- Workflow actions --}}
        <div class="work-actions" aria-label="Tindakan karya">
            @can('submit', $work) @include('partials.workflow-action', ['action'=>'submit','label'=>'Ajukan verifikasi']) @endcan
            @can('verify', $work)
                @if($work->repository_status === 'submitted')
                    @include('partials.workflow-action', ['action'=>'review','label'=>'Mulai pemeriksaan'])
                @elseif($work->repository_status === 'pending_verification')
                    @include('partials.workflow-action', ['action'=>'approve','label'=>'Setujui verifikasi'])
                    @include('partials.workflow-action', ['action'=>'revision','label'=>'Minta revisi','secondary'=>true])
                    @include('partials.workflow-action', ['action'=>'reject','label'=>'Tolak pengajuan','secondary'=>true])
                @endif
            @endcan
            @can('publish', $work)
                @if($type !== 'publikasi' || $readiness['ready'])
                    @include('partials.workflow-action', ['action'=>'publish','label'=>'Terbitkan karya'])
                @endif
            @endcan
            @can('unpublish', $work) @include('partials.workflow-action', ['action'=>'unpublish','label'=>'Tarik dari repositori','secondary'=>true]) @endcan
            @can('reopen', $work) @include('partials.workflow-action', ['action'=>'reopen','label'=>'Buka revisi','secondary'=>true]) @endcan
            @can('archive', $work) @include('partials.workflow-action', ['action'=>'archive','label'=>'Arsipkan','secondary'=>true]) @endcan
            @can('restore', $work) @include('partials.workflow-action', ['action'=>'restore','label'=>'Pulihkan karya','secondary'=>true]) @endcan
        </div>

        {{-- Processing state --}}
        @if (
            $type === 'publikasi' &&
            $work->repository_status === 'publishing'
        )
            <p class="helper">
                Penerbitan sedang diproses. Jika lebih dari beberapa menit, hubungi pengelola untuk memeriksa antrean.

                <a
                    class="arrow-link"
                    href="{{ route('admin.works.show', [$type, $work->id]) }}"
                >
                    Muat ulang status
                </a>
            </p>
        @endif

        @if (
            $type === 'publikasi' &&
            $work->repository_status === 'published'
        )
            <p class="readiness-success">
                Karya sudah terbit. Akses tetap mengikuti pengaturan metadata dan dokumen.
            </p>
        @endif
    </section>

    @if($type === 'publikasi' && $work->repository_status === 'publishing')
        <div class="notice" data-publish-watch="{{ route('admin.works.status', [$type, $work->id]) }}" data-version="{{ $work->lock_version }}">
            <p role="status" data-publish-message>Memeriksa status penerbitan…</p>
            <a class="arrow-link" href="{{ route('admin.works.show', [$type, $work->id]) }}" data-publish-refresh hidden>Muat status terbaru</a>
        </div>
    @endif
<section class="panel mb-5" aria-labelledby="work-cover-heading">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h2 id="work-cover-heading" class="!text-lg">Sampul karya</h2><p class="mt-1 text-sm">PNG maksimal 3 MB. Rasio potret 3:4 direkomendasikan.</p></div>
    </div>
    <div class="mt-4 flex flex-wrap items-start gap-5">
        <img src="{{ $work->cover ? route('works.cover.show', [$type, $work->id]) : asset('images/cover-placeholder.svg') }}" alt="Sampul {{ $work->judul }}" width="144" height="192" loading="lazy" class="h-48 w-36 shrink-0 rounded-xl border border-[var(--line)] bg-[var(--surface)] object-cover">
        <div class="min-w-0 flex-1">
            @if(! $work->archived_at && (auth()->user()->isStaff() || \Illuminate\Support\Facades\Gate::allows('update', $work)))
                <form action="{{ route('admin.works.cover.store', [$type, $work->id]) }}" method="post" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <input type="hidden" name="lock_version" value="{{ $work->lock_version }}">
                    <label class="block text-sm font-medium text-[var(--ink)]" for="work-cover-file">Unggah sampul PNG</label>
                    <input id="work-cover-file" name="cover" type="file" accept="image/png" required class="block w-full rounded-lg border border-[var(--field-line)] p-3 text-sm file:mr-3 file:rounded file:border-0 file:bg-[var(--blue-soft)] file:px-3 file:py-2">
                    <x-input-error :messages="$errors->get('cover')" class="mt-2" />
                    <button type="submit" class="button">{{ $work->cover ? 'Ganti sampul' : 'Simpan sampul' }}</button>
                </form>
                @if($work->cover)
                    <form action="{{ route('admin.works.cover.destroy', [$type, $work->id]) }}" method="post" class="mt-3">
                        @csrf @method('delete')
                        <input type="hidden" name="lock_version" value="{{ $work->lock_version }}">
                        <button class="text-sm font-medium text-[var(--muted)] underline underline-offset-4" type="submit">Hapus sampul</button>
                    </form>
                @endif
            @else
                <p class="text-sm">Sampul dapat dikelola pemilik saat karya masih dapat diedit, atau oleh Admin/Operator untuk karya terbit.</p>
            @endif
        </div>
    </div>
</section>
<div class="detail-admin-grid">

        <div>

            {{-- Work metadata --}}
            <section class="panel">
                <h2>Informasi karya</h2>
                @if($type === 'publikasi') @include('partials.journal-metadata') @endif

                <dl>
                    @foreach (
                        $work->only($work->getFillable())
                        as $field => $value
                    )
                        @if (
                            filled($value) &&
                            !in_array(
                                $field,
                                ['fakultas_id', 'program_studi_id', 'judul', 'abstrak', 'keterangan', 'jurnal_id', 'publication_type', 'journal_name_manual', 'indexing_mode', 'sinta_rank', 'scopus_indexed', 'scopus_quartile', 'indexing_year', 'indexing_source'],
                                true
                            )
                        )
                            <div>
                                <dt>
                                    {{ ['jurnal_id' => 'Jurnal', 'penerbit_id' => 'Penerbit', 'academic_status' => 'Status akademik', 'public_visibility' => 'Akses metadata', 'doi' => 'DOI', 'isbn' => 'ISBN'][$field] ?? str_replace('_', ' ', $field) }}
                                </dt>

                                <dd>
                                    @if ($value instanceof \DateTimeInterface)
                                        {{ $value->format('d M Y') }}
                                    @elseif ($field === 'jurnal_id')
                                        {{ $work->jurnal?->nama_jurnal ?? 'Belum dipilih' }}
                                    @elseif ($field === 'penerbit_id')
                                        {{ $work->penerbit?->nama ?? 'Belum dipilih' }}
                                    @elseif ($field === 'public_visibility')
                                        {{ ['public' => 'Publik saat terbit', 'internal' => 'Internal', 'private' => 'Privat tim karya', 'restricted' => 'Terbatas'][$value] ?? $value }}
                                    @elseif ($field === 'academic_status')
                                        {{ ['draft' => 'Draf', 'submitted' => 'Diajukan ke jurnal', 'under_review' => 'Dalam penilaian jurnal', 'accepted' => 'Diterima jurnal', 'published' => 'Terbit di jurnal', 'rejected' => 'Ditolak jurnal'][$value] ?? $value }}
                                    @else
                                        {{ $value }}
                                    @endif
                                </dd>
                            </div>
                        @endif
                    @endforeach
                </dl>

                @if ($type === 'publikasi')
                    <div class="detail-divider"></div>

                    <h3>Abstrak</h3>

                    <p class="prose-text">
                        {{ $work->abstrak ?: 'Belum diisi.' }}
                    </p>
                @endif

                @if ($work->keterangan)
                    <div class="detail-divider"></div>

                    <h3>Catatan internal</h3>

                    <p class="prose-text">
                        {{ $work->keterangan }}
                    </p>
                @endif
            </section>

            {{-- Publication history --}}
            @if ($type === 'publikasi')
                <section class="panel" id="publishing-history">
                    <h2>Riwayat penerbitan</h2>

                    <div class="activity-feed">
                        @forelse ($attempts as $attempt)
                            <article class="activity-entry">
                                <span class="activity-dot" aria-hidden="true"></span>
                                <div class="activity-body">
                                    <div class="activity-heading">
                                        <h3>Percobaan penerbitan ke-{{ $attempt->attempt_number }}</h3>
                                        <time datetime="{{ $attempt->created_at->toIso8601String() }}">{{ $attempt->created_at->format('d M Y, H:i') }}</time>
                                    </div>
                                    <p>{{ match ($attempt->status) {
                                        'queued' => 'Penerbitan menunggu pemeriksaan akhir.',
                                        'processing' => 'Metadata dan dokumen sedang diperiksa.',
                                        'success' => 'Karya berhasil diterbitkan sesuai pengaturan aksesnya.',
                                        'failed' => 'Penerbitan belum berhasil. Periksa keterangan di bawah sebelum mencoba lagi.',
                                        'cancelled' => 'Percobaan ini dibatalkan dan tidak menerbitkan karya.',
                                        default => 'Hasil penerbitan belum tersedia.',
                                    } }}</p>
                                    @if ($attempt->error_message)<blockquote>{{ $attempt->error_message }}</blockquote>@endif
                                </div>
                            </article>
                        @empty
                            <p class="helper">Belum ada percobaan penerbitan.</p>
                        @endforelse
                    </div>
                    @if ($attempts->hasPages())
                        <div class="history-pagination">{{ $attempts->links('partials.history-pagination') }}</div>
                    @endif

                    <div class="detail-divider"></div>
                    <h3 id="verification-history">Catatan verifikasi</h3>
                    <div class="activity-feed">
                        @forelse ($verifications as $verification)
                            <article class="activity-entry">
                                <span class="activity-dot" aria-hidden="true"></span>
                                <div class="activity-body">
                                    <div class="activity-heading">
                                        <h3>{{ match ($verification->status) {
                                            'approved' => 'Verifikasi disetujui',
                                            'rejected' => 'Pengajuan ditolak',
                                            'revision_required' => 'Perbaikan diperlukan',
                                            default => 'Hasil pemeriksaan dicatat',
                                        } }}</h3>
                                        <time datetime="{{ $verification->created_at->toIso8601String() }}">{{ $verification->created_at->format('d M Y, H:i') }}</time>
                                    </div>
                                    <span class="activity-by">{{ $verification->verifier?->nama ?? 'Pengelola' }}</span>
                                    <p>{{ $verification->notes ?: 'Tidak ada catatan tambahan.' }}</p>
                                </div>
                            </article>
                        @empty
                            <p class="helper">Pemeriksaan belum dilakukan.</p>
                        @endforelse
                    </div>
                    @if ($verifications->hasPages())
                        <div class="history-pagination">{{ $verifications->links('partials.history-pagination') }}</div>
                    @endif
                </section>
            @endif

            {{-- Audit history --}}
            <section class="panel">
                <div class="panel-heading">
                    <h2 id="activity-history">Jejak perubahan</h2>

                    <span class="muted">
                        Riwayat aktivitas
                    </span>
                </div>

                @include('partials.audit-timeline', [
                    'histories' => $histories,
                ])
            </section>
        </div>

        {{-- Documents --}}
        <aside>
            <section class="panel">
                <div class="panel-heading">
                    <h2>Dokumen</h2>

                    <span>
                        {{ $work->dokumen->count() }} file
                    </span>
                </div>

                @forelse ($work->dokumen as $document)
                    <div class="document-block">
                        <h3 class="document-type">{{ \App\Support\DocumentTypes::label($document->jenis_dokumen) }}</h3>

                        <a
                            class="document-link"
                            href="{{ route('documents.download', $document) }}"
                        >
                            <span class="pdf-icon">
                                PDF
                            </span>

                            <span>
                                <strong>
                                    {{ $document->nama_file_asli ?? $document->nama_file }}
                                </strong>

                                <small>
                                    Versi {{ $document->current_version }}
                                    ·
                                    {{ $document->publik
                                        ? 'Publik saat terbit'
                                        : 'Privat' }}
                                </small>
                            </span>

                            <b aria-hidden="true">↓</b>
                        </a>

                        @can('update', $work)

                            {{-- Remove document --}}
                            <form
                                class="compact-form"
                                method="POST"
                                action="{{ route('admin.documents.destroy', $document) }}"
                                data-confirm="Lepas dokumen ini dari karya?"
                            >
                                @csrf
                                @method('DELETE')

                                <input
                                    type="hidden"
                                    name="lock_version"
                                    value="{{ $work->lock_version }}"
                                >

                                <button
                                    type="submit"
                                    class="text-button text-danger"
                                >
                                    Lepas dokumen
                                </button>
                            </form>

                            {{-- Replace document --}}
                            <details>
                                <summary>
                                    Unggah versi baru
                                </summary>

                                <form
                                    class="compact-form"
                                    method="POST"
                                    enctype="multipart/form-data"
                                    action="{{ route('admin.documents.replace', $document) }}"
                                >
                                    @csrf

                                    @include('partials.document-fields', [
                                        'document' => $document,
                                    ])

                                    <button
                                        type="submit"
                                        class="button button-small"
                                    >
                                        Simpan versi baru
                                    </button>
                                </form>
                            </details>

                            {{-- Visibility --}}
                            <form
                                class="compact-form"
                                method="POST"
                                action="{{ route('admin.documents.visibility', $document) }}"
                            >
                                @csrf
                                @method('PATCH')

                                <input
                                    type="hidden"
                                    name="lock_version"
                                    value="{{ $work->lock_version }}"
                                >

                                <input
                                    type="hidden"
                                    name="publik"
                                    value="{{ $document->publik ? 0 : 1 }}"
                                >

                                <button
                                    type="submit"
                                    class="button button-secondary button-small"
                                >
                                    {{ $document->publik
                                        ? 'Jadikan privat'
                                        : 'Izinkan akses publik saat terbit' }}
                                </button>
                            </form>
                        @endcan

                        {{-- Document versions --}}
                        @if (
                            ($versions[$document->id] ?? collect())->isNotEmpty()
                        )
                            <details>
                                <summary>
                                    Riwayat versi
                                </summary>

                                @foreach ($versions[$document->id] as $version)
                                    <p class="helper">
                                        <a
                                            href="{{ route('admin.documents.version', $version) }}"
                                        >
                                            Versi {{ $version->version_number }}
                                            ·
                                            {{ $version->created_at->format('d M Y') }}
                                            ↓
                                        </a>
                                    </p>
                                @endforeach
                            </details>
                        @endif
                    </div>
                @empty
                    <p class="helper">
                        Belum ada dokumen.
                    </p>
                @endforelse

                <div class="pagination-wrap">
                    {{ $versionPage->withQueryString()->links() }}
                </div>

                {{-- Add document --}}
                @can('update', $work)
                    <div class="detail-divider"></div>

                    <h3>Tambah dokumen</h3>

                    <form
                        class="compact-form"
                        method="POST"
                        enctype="multipart/form-data"
                        action="{{ route('admin.works.documents', [$type, $work->id]) }}"
                    >
                        @csrf

                        @include('partials.document-fields', [
                            'document' => null,
                        ])

                        <button
                            type="submit"
                            class="button"
                        >
                            Unggah PDF
                        </button>
                    </form>
                @endcan
            </section>
        </aside>
    </div>
@endsection

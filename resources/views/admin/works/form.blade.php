@extends('layouts.repository')
@section('title', ($work->exists ? 'Ubah ' : 'Tambah ') . strtolower(\App\Support\WorkType::TYPES[$type]))
@section('content')
    <div class="page-heading">
        <div>
            <a class="breadcrumb" href="{{ route('admin.works.index', $type) }}">← Kembali ke {{ strtolower(\App\Support\WorkType::TYPES[$type]) }}</a>
            <h1>{{ $work->exists ? 'Ubah' : 'Tambah' }} {{ strtolower(\App\Support\WorkType::TYPES[$type]) }}</h1>
            <p>Lengkapi data karya, identitas terbitan, lalu file pendukung.</p>
        </div>
    </div>
    <form novalidate data-work-wizard data-unsaved-guard method="post" enctype="multipart/form-data"
        action="{{ $work->exists ? route('admin.works.update', [$type, $work->id]) : route('admin.works.store', $type) }}">
        @csrf
        @if ($work->exists)
            @method('PUT')<input type="hidden" name="lock_version" value="{{ $work->lock_version }}">
        @endif
        <nav class="form-steps" aria-label="Tahap pengisian">
            @foreach(['Data karya','Identitas terbitan','File pendukung'] as $step=>$label)
                <button type="button" data-step-target="{{ $step }}"><span>{{ $step + 1 }}</span>{{ $label }}</button>
            @endforeach
        </nav>
        <div data-form-step="0" aria-label="Data karya">
            <section class="panel">
                <div class="form-section-title"><h2>Data karya</h2><span>* Wajib diisi</span></div>
                <div class="form-grid">
                    @include('partials.field', ['name'=>'judul','label'=>'Judul karya','value'=>$work->judul,'wide'=>true,'required'=>true])
                    @include('partials.field', ['name'=>\App\Support\WorkType::year($type),'label'=>'Tahun terbit / pencatatan','kind'=>'number','value'=>$work->{\App\Support\WorkType::year($type)}])
                    @include('partials.field', ['name'=>'public_visibility','label'=>'Akses metadata setelah terbit','kind'=>'select','value'=>$work->public_visibility ?? 'private','required'=>true,'options'=>['private'=>'Privat tim karya','public'=>'Publik','internal'=>'Internal tim karya','restricted'=>'Terbatas tim karya']])
                    @if($type === 'publikasi')
                        @include('partials.field', ['name'=>'fakultas_id','label'=>'Fakultas asal jurnal','kind'=>'select','lookup'=>route('lookups','fakultas'),'value'=>$work->fakultas_id,'options'=>$faculties->pluck('nama_fakultas','id')])
                        @include('partials.field', ['name'=>'program_studi_id','label'=>'Program studi asal jurnal','kind'=>'select','lookup'=>route('lookups','program-studi'),'value'=>$work->program_studi_id,'options'=>$programs->mapWithKeys(fn($p) => [$p->id => $p->nama.' — '.$p->fakultas?->nama_fakultas])])
                        <p class="helper form-field-wide">Pilih asal jurnal dari Data Referensi. Fakultas boleh dikosongkan jika prodi sudah dipilih; sistem akan mengikuti fakultas prodi tersebut. Data ini terpisah dari afiliasi penulis.</p>
                        @include('partials.field', ['name'=>'publication_type','label'=>'Jenis karya','kind'=>'select','value'=>$work->publication_type ?? ($work->exists ? null : 'artikel_jurnal'),'options'=>\App\Support\JournalMetadata::TYPES])
                        @include('partials.field', ['name'=>'abstrak','label'=>'Abstrak','kind'=>'textarea','value'=>$work->abstrak,'wide'=>true,'rows'=>5])
                    @elseif($type === 'buku')
                        @include('partials.field', ['name'=>'kategori','label'=>'Kategori buku','value'=>$work->kategori])
                    @else
                        @include('partials.field', ['name'=>'jenis','label'=>'Jenis HKI','value'=>$work->jenis,'required'=>true])
                    @endif
                </div>
            </section>
            <section class="panel">
                <div class="form-section-title"><h2>Penulis / pencipta</h2></div>
                <p class="helper section-hint">Ketik penulis baru atau pilih nama yang sudah tersedia. Nama baru akan ditambahkan ke data referensi saat karya disimpan.</p>
                @php($authorRows = old('authors', $work->exists ? $work->{\App\Support\WorkType::authors($work)}->map(fn($a) => ['id'=>$a->id,'corresponding'=>$a->pivot->penulis_korespondensi ?? false,'principal'=>$a->pivot->peneliti_utama ?? false])->toArray() : [['id'=>auth()->user()->penulis?->id]]))
                <div data-repeater data-next="{{ count($authorRows) }}" data-max="50">
                    <div data-rows>@foreach($authorRows as $index=>$row) @include('partials.author-row') @endforeach</div>
                    <template data-template>@include('partials.author-row', ['index'=>'__INDEX__','row'=>[]])</template>
                    <button class="button button-secondary button-small" type="button" data-add>Tambah penulis</button>
                </div>
                <p class="helper">Urutan teratas menjadi penulis pertama. Penambahan referensi tidak membuat akun login baru.</p>
            </section>
        </div>
        <div data-form-step="1" aria-label="Identitas terbitan">
            <section class="panel">
                <div class="form-section-title"><h2>Identitas terbitan</h2><span>Isi yang tersedia</span></div>
                @if($type === 'publikasi')
                    <div class="source-choice" data-source-choice>
                        <fieldset class="choice-tabs"><legend>Jurnal / prosiding</legend>
                            @foreach(['manual'=>'Ketik nama','reference'=>'Pilih rekomendasi'] as $key=>$label)
                                <label><input type="radio" name="journal_source" value="{{ $key }}" @checked(old('journal_source', $work->jurnal_id ? 'reference' : 'manual') === $key)> {{ $label }}</label>
                            @endforeach
                        </fieldset>
                        <div data-source-panel="manual">@include('partials.field', ['name'=>'journal_name_manual','label'=>'Nama jurnal / prosiding','value'=>$work->journal_name_manual,'required'=>true])</div>
                        <div data-source-panel="reference">@include('partials.field', ['lookup'=>route('lookups','jurnal'),'name'=>'jurnal_id','label'=>'Rekomendasi jurnal / prosiding','kind'=>'select','value'=>$work->jurnal_id,'options'=>$journals->pluck('nama_jurnal','id')])</div>
                    </div>
                    <div class="form-grid">
                        @include('partials.field', ['name'=>'doi','label'=>'DOI','value'=>$work->doi])
                        @include('partials.field', ['name'=>'isbn','label'=>'ISBN prosiding, jika ada','value'=>$work->isbn])
                        @include('partials.field', ['name'=>'issn_cetak','label'=>'ISSN cetak','value'=>$work->issn_cetak ?? $work->jurnal?->issn_cetak])
                        @include('partials.field', ['name'=>'issn_elektronik','label'=>'ISSN elektronik','value'=>$work->issn_elektronik ?? $work->jurnal?->issn_elektronik])
                        @include('partials.field', ['name'=>'tanggal_publikasi','label'=>'Tanggal terbit','kind'=>'date','value'=>$work->tanggal_publikasi?->format('Y-m-d')])
                        @include('partials.field', ['name'=>'academic_status','label'=>'Status artikel di jurnal','kind'=>'select','value'=>$work->academic_status ?? 'draft','options'=>['draft'=>'Draf','submitted'=>'Diajukan ke jurnal','under_review'=>'Ditelaah jurnal','accepted'=>'Diterima jurnal','published'=>'Terbit di jurnal','rejected'=>'Ditolak jurnal']])
                    </div>
                    <p class="helper">DOI bisa ditempel sebagai tautan doi.org. ISBN digunakan untuk prosiding/buku; ISSN untuk jurnal.</p>
                @elseif($type === 'buku')
                    <div class="source-choice" data-source-choice>
                        <fieldset class="choice-tabs"><legend>Penerbit</legend>
                            @foreach(['manual'=>'Ketik nama','reference'=>'Pilih rekomendasi'] as $key=>$label)
                                <label><input type="radio" name="publisher_source" value="{{ $key }}" @checked(old('publisher_source', $work->penerbit_id ? 'reference' : 'manual') === $key)> {{ $label }}</label>
                            @endforeach
                        </fieldset>
                        <div data-source-panel="manual">@include('partials.field', ['name'=>'publisher_name','label'=>'Nama penerbit','required'=>true])</div>
                        <div data-source-panel="reference">@include('partials.field', ['name'=>'penerbit_id','label'=>'Rekomendasi penerbit','kind'=>'select','lookup'=>route('lookups','penerbit'),'value'=>$work->penerbit_id,'options'=>$publishers->pluck('nama','id')])</div>
                    </div>
                    <div class="form-grid">
                        @include('partials.field', ['name'=>'isbn','label'=>'ISBN (10 atau 13 digit)','value'=>$work->isbn])
                        @include('partials.field', ['name'=>'jumlah_halaman','label'=>'Jumlah halaman','kind'=>'number','value'=>$work->jumlah_halaman])
                        @include('partials.field', ['name'=>'tanggal_publikasi','label'=>'Tanggal terbit','kind'=>'date','value'=>$work->tanggal_publikasi?->format('Y-m-d')])
                    </div>
                @else
                    <div class="form-grid">
                        @foreach(['periode'=>'Periode','nomor_permohonan'=>'Nomor permohonan','nomor_pencatatan'=>'Nomor pencatatan'] as $name=>$label)
                            @include('partials.field', ['name'=>$name,'label'=>$label,'value'=>$work->$name])
                        @endforeach
                        @foreach(['tanggal_permohonan'=>'Tanggal permohonan','tanggal_pencatatan'=>'Tanggal pencatatan'] as $name=>$label)
                            @include('partials.field', ['name'=>$name,'label'=>$label,'kind'=>'date','value'=>$work->$name?->format('Y-m-d')])
                        @endforeach
                    </div>
                @endif
                @include('partials.field', ['name'=>'tautan_eksternal','label'=>'Tautan sumber asli','kind'=>'url','value'=>$work->tautan_eksternal])
            </section>
            @include('partials.work-indexing')
        </div>
        <div data-form-step="2" aria-label="File pendukung">
            <section class="panel">
                <div class="form-section-title"><h2>File pendukung</h2><span>Opsional</span></div>
                <p class="helper section-hint">Pilih jenis file sebelum mengunggah. Maksimal 5 PDF dengan total ukuran 20 MB.</p>
                @if($work->exists && $work->dokumen->isNotEmpty())
                    <div class="existing-files">
                        @foreach($work->dokumen as $document)
                            <div><strong>{{ \App\Support\DocumentTypes::label($document->jenis_dokumen) }}</strong><span>{{ $document->nama_file_asli ?? $document->nama_file }}</span></div>
                        @endforeach
                    </div>
                    <p class="helper">File di atas tetap tersimpan. Penggantian versi atau pelepasan file dilakukan dari halaman detail.</p>
                @endif
                @if($errors->has('attachments.*'))<p class="helper text-danger">Pilih ulang file setelah memperbaiki isian. Browser tidak dapat mengisi ulang pilihan file.</p>@endif
                @php($attachmentRows = old('attachments', [['jenis_dokumen'=>'dokumen_utama','publik'=>0]]))
                <div data-repeater data-next="{{ count($attachmentRows) }}" data-max="5">
                    <div data-rows>@foreach($attachmentRows as $index=>$row) @include('partials.attachment-row') @endforeach</div>
                    <template data-template>@include('partials.attachment-row', ['index'=>'__INDEX__','row'=>[]])</template>
                    <button type="button" class="button button-secondary button-small" data-add>Tambah file pendukung</button>
                </div>
            </section>
            <section class="panel">
                @include('partials.field', ['name'=>'keterangan','label'=>'Catatan internal','kind'=>'textarea','value'=>$work->keterangan,'rows'=>3])
                <p class="helper">Karya disimpan sebagai draf. Pengajuan verifikasi tetap dilakukan dari halaman detail.</p>
            </section>
        </div>
        <div class="wizard-actions">
            <a href="{{ route('admin.works.index',$type) }}">Batal</a>
            <div>
                <button type="button" class="button button-secondary" data-step-back hidden>Kembali</button>
                <button type="button" class="button" data-step-next hidden>Lanjut</button>
                <button type="submit" class="button" data-step-save>{{ $work->exists ? 'Simpan perubahan' : 'Simpan draf' }}</button>
            </div>
        </div>
    </form>
@endsection

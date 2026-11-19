@extends('layouts.repository')
@section('title', 'Repositori akademik')
@section('content')
<section class="library-hero container">
    <div class="library-intro">
        <span class="eyebrow">Repositori akademik · UMIBA</span>
        <h1>Penelitian yang bisa<br>Anda <em>temukan.</em></h1>
        <p>Telusuri jurnal, buku, dan kekayaan intelektual dari komunitas Universitas Mitra Bangsa.</p>
        <form class="library-search" action="{{ route('repository.search') }}" method="get" role="search">
            <label class="sr-only" for="hero-q">Cari karya</label><x-icon name="search" size="20" /><input id="hero-q" name="q" maxlength="150" placeholder="Judul, nama penulis, atau DOI"><button class="button" type="submit">Cari</button>
        </form>
        <div class="quick-search"><span>Jelajahi:</span><a href="{{ route('repository.search',['type'=>'publikasi']) }}">Jurnal</a><a href="{{ route('repository.search',['type'=>'buku']) }}">Buku</a><a href="{{ route('repository.search',['type'=>'hki']) }}">HKI</a></div>
        <div class="library-caption"><span>01 / Koleksi institusi</span><span>Metadata terbuka, akses dokumen sesuai izin.</span></div>
    </div>
    <figure class="library-visual"><img src="{{ asset('images/library.webp') }}" width="1400" height="1294" alt="Rak buku dan lampu baca di perpustakaan" fetchpriority="high"><figcaption>Ruang untuk membaca lebih jauh.<small>Ilustrasi perpustakaan · Janko Ferlič / Unsplash</small></figcaption></figure>
</section>
<section class="catalog-stats container" aria-label="Statistik repositori">
    @foreach($stats as $label=>$value)<div><strong>{{ number_format($value,0,',','.') }}</strong><span>{{ $label }}{{ $label !== 'Dosen' ? ' tersedia untuk publik' : ' dalam direktori' }}</span></div>@endforeach
</section>
<section class="section container latest-section">
    <div class="section-heading"><div><div class="eyebrow">Dari komunitas akademik</div><h2>Karya terbaru</h2><p>Publikasi, buku, dan HKI yang baru tersedia.</p></div><a class="arrow-link" href="{{ route('repository.search') }}">Lihat semua karya <x-icon name="arrow-right" size="18" /></a></div>
    <div class="card-grid card-grid--latest">@forelse($latest as $item) @include('partials.work-card') @empty<div class="empty-state"><h3>Koleksi sedang disiapkan</h3><p>Jurnal yang diterbitkan dengan akses publik akan tampil di sini.</p></div>@endforelse</div>
</section>
<section class="browse-section container">
    <div class="browse-title"><span class="eyebrow">Cari lebih terarah</span><h2>Mulai dari <br>koleksi yang tepat.</h2><p>Gunakan tahun, prodi, dan pengindeksan untuk mempersempit pencarian.</p><a class="arrow-link" href="{{ route('repository.search') }}">Buka pencarian lengkap <x-icon name="arrow-right" size="18" /></a></div>
    <div class="collection-directory">
        @foreach(['publikasi'=>['01','Jurnal & artikel','Artikel jurnal, prosiding, dan catatan penelitian.'], 'buku'=>['02','Buku & monograf','Bahan ajar dan referensi akademik.'], 'hki'=>['03','Kekayaan intelektual','Karya cipta dan inovasi yang telah dicatat.']] as $key=>[$number,$label,$detail])
            <a href="{{ route('repository.search',['type'=>$key]) }}"><span>{{ $number }}</span><div><h3>{{ $label }}</h3><p>{{ $detail }}</p></div><x-icon name="arrow-up-right" size="24" /></a>
        @endforeach
    </div>
</section>
<section class="research-section container">
    <figure><img src="{{ asset('images/research.webp') }}" width="1000" height="662" loading="lazy" decoding="async" alt="Proses mencatat dan menelaah bahan penelitian"><figcaption>Ilustrasi kegiatan riset · UX Indonesia / Unsplash</figcaption></figure>
    <div class="research-copy"><span class="eyebrow">Baca, telusuri, rujuk</span><h2>Dari satu artikel,<br>ke referensi berikutnya.</h2><p>Periksa identitas jurnal, tahun, DOI, dan informasi SINTA atau Scopus yang tercatat sebelum menggunakan karya sebagai rujukan.</p>
        <div class="index-shortcuts"><a href="{{ route('repository.search',['type'=>'publikasi','sinta_rank'=>1]) }}">Jurnal SINTA 1 <x-icon name="arrow-up-right" size="16" /></a><a href="{{ route('repository.search',['type'=>'publikasi','scopus_indexed'=>1]) }}">Terindeks Scopus <x-icon name="arrow-up-right" size="16" /></a><a href="{{ route('repository.lecturers') }}">Temukan dosen <x-icon name="arrow-up-right" size="16" /></a></div>
    </div>
</section>
<section class="reading-guide container">
    <div><span class="eyebrow">Panduan singkat</span><h2>Menggunakan repositori</h2></div>
    <div class="guide-questions">
        <details><summary>Bagaimana menemukan karya dari program studi tertentu?</summary><p>Buka Jelajahi karya, pilih fakultas atau program studi pada filter, lalu terapkan. Afiliasi mengikuti data dosen pada daftar penulis karya.</p></details>
        <details><summary>Mengapa tidak semua karya menyediakan PDF?</summary><p>Akses metadata dan dokumen diatur terpisah. PDF hanya tersedia jika pengelola membuka aksesnya dan persyaratan keamanan terpenuhi.</p></details>
        <details><summary>Bagaimana mengirim karya ke repositori?</summary><p>Masuk ke ruang kerja, isi tiga tahap data karya, identitas terbitan, dan file pendukung. Simpan draf, lalu ajukan verifikasi. Ikuti catatan pemeriksaan sebelum karya diterbitkan.</p></details>
    </div>
</section>
<section class="container submission-guide" id="panduan" aria-labelledby="guide-heading">
    <div><span class="eyebrow">DARI KARYA KE REPOSITORI</span><h2 id="guide-heading">Siapkan karya, lengkapi datanya.</h2><p>Simpan draf terlebih dahulu. Periksa kembali sebelum mengajukannya untuk verifikasi.</p><a class="arrow-link" href="{{ route('admin.works.create','publikasi') }}">Mulai menambahkan karya <x-icon name="arrow-right" size="18" /></a></div>
    <ol><li><strong>Data karya</strong><span>Isi judul dan penulis. Referensi tersedia sebagai rekomendasi.</span></li><li><strong>Identitas terbitan</strong><span>Tambahkan jurnal, DOI, ISBN, dan informasi indeks yang tersedia.</span></li><li><strong>File pendukung</strong><span>Pilih jenis file dan aksesnya, lalu simpan sebagai draf.</span></li></ol>
</section>
@endsection

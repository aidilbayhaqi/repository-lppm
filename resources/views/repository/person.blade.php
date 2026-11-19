@extends('layouts.repository')
@section('title', $name)
@section('content')<section class="container section">
        <a class="breadcrumb" href="{{ route('repository.search') }}">← Jelajahi repositori</a>
        <div class="eyebrow">{{ $subtitle }}</div>
        <h1>{{ $name }}</h1>
        <p class="lead">{{ $results->total() }} karya tersedia untuk publik.</p>
        <div class="results-list">
            @forelse($results as $item)
                @include('partials.work-card')@empty
                <div class="empty-state">
                    <h2>Belum ada karya publik</h2>
                    <p>Karya yang diterbitkan akan tampil di sini.</p>
                </div>
            @endforelse
        </div>
        <div class="pagination-wrap">{{ $results->links() }}</div>
</section>@endsection

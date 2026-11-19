<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Repositori Akademik') · OSDJ LPPM UMIBA</title>
    <meta name="description"
        content="Repositori akademik LPPM Universitas Mitra Bangsa. Jelajahi jurnal, buku, dan kekayaan intelektual.">
    @include('partials.theme-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <!-- UMIBA-BRANDING-FAVICONS-v1 -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
</head>
@php($internal = request()->is('admin*', 'profile', 'dashboard'))

<body class="{{ $internal ? 'workspace-body' : 'public-body' }}">
    <a class="skip-link" href="#main">Langsung ke konten</a>
    @if ($internal)
        <div class="workspace">
            <div class="sidebar-backdrop" data-sidebar-backdrop hidden></div>
            <aside class="sidebar" id="workspace-navigation" aria-label="Menu ruang kerja" tabindex="-1" data-sidebar>
                <div class="sidebar-brand">
                    <a class="brand" href="{{ route('admin.dashboard') }}"><x-brand /></a>
                    <button class="icon-button sidebar-close" type="button" aria-label="Tutup menu"
                        data-sidebar-close><x-icon name="close" /></button>
                </div>
                <nav aria-label="Navigasi administrasi">
                    <div class="nav-caption">Ruang kerja</div>
                    <a @class([
                        'nav-link',
                        'active' => request()->routeIs('admin.dashboard'),
                    ]) @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif
                        href="{{ route('admin.dashboard') }}"><x-icon name="grid" />Ringkasan</a>
                    @foreach (\App\Support\WorkType::TYPES as $key => $label)
                        @php($active = request()->route('type') === $key && request()->routeIs('admin.works.*'))
                        <a @class(['nav-link', 'active' => $active]) @if ($active) aria-current="page" @endif
                            href="{{ route('admin.works.index', $key) }}">
                            <x-icon :name="$key === 'publikasi' ? 'document' : ($key === 'buku' ? 'book' : 'shield')" />
                            {{ $key === 'hki' ? 'Kekayaan intelektual' : $label }}
                        </a>
                    @endforeach
                    @can('manage-reference')
                        <a @class(['nav-link', 'active' => request()->routeIs('admin.import.*')]) @if (request()->routeIs('admin.import.*')) aria-current="page" @endif
                            href="{{ route('admin.import.index') }}"><x-icon name="upload" />Import data</a>
                        <div class="nav-caption">Pengelolaan</div>
                        <details class="nav-group" @if (request()->routeIs('admin.reference.*')) open @endif>
                            <summary><x-icon name="layers" /><span>Data referensi</span><x-icon name="chevron-down"
                                    class="nav-chevron" size="16" /></summary>
                            <div class="nav-submenu">
                                @foreach (\App\Support\ReferenceCatalog::all() as $key => $def)
                                    @php($active = request()->route('entity') === $key)
                                    <a @class(['nav-link', 'active' => $active])
                                        @if ($active) aria-current="page" @endif
                                        href="{{ route('admin.reference.index', $key) }}">{{ $def['label'] }}</a>
                                @endforeach
                            </div>
                        </details>
                    @endcan
                    @can('manage-users')
                        <a @class(['nav-link', 'active' => request()->routeIs('admin.users.*')]) @if (request()->routeIs('admin.users.*')) aria-current="page" @endif
                            href="{{ route('admin.users.index') }}"><x-icon name="users" />Pengguna & akses</a>
                    @endcan
                </nav>
                <div class="sidebar-foot">
                    <a href="{{ route('home') }}"><x-icon name="globe" size="18" /><span>Halaman
                            publik</span><x-icon name="arrow-up-right" size="16" /></a>
                    <small>Repositori akademik UMIBA</small>
                </div>
            </aside>
            <div class="workspace-main" data-workspace-main>
                <header class="workspace-header">
                    <div class="workspace-location">
                        <button class="icon-button sidebar-toggle" type="button" aria-label="Buka menu navigasi"
                            aria-controls="workspace-navigation" aria-expanded="false" data-sidebar-toggle><x-icon
                                name="menu" /></button>
                        <span class="header-parent">Ruang kerja <span aria-hidden="true">/</span></span>
                        <span class="header-current">@yield('title', 'Ringkasan')</span>
                    </div>
                    <div class="user-menu"><x-theme-picker />
                        <a class="user-profile" href="{{ route('profile.edit') }}"
                            aria-label="Profil {{ auth()->user()->nama }}">
                            <span class="avatar">@if(auth()->user()->profile_photo_path)<img src="{{ route('profile.photo.show') }}" alt="" class="h-full w-full rounded-full object-cover">@else{{ mb_substr(auth()->user()->nama, 0, 1) }}@endif</span>
                            <span
                                class="user-name">{{ auth()->user()->nama }}<small>{{ str_replace('_', ' ', auth()->user()->role) }}</small></span>
                        </a>
                        <form method="post" action="{{ route('logout') }}">@csrf<button class="icon-button"
                                title="Keluar" aria-label="Keluar" type="submit"><x-icon name="logout"
                                    size="18" /></button></form>
                    </div>
                </header>
                <main id="main" class="workspace-content" tabindex="-1">@include('partials.feedback')
                    @yield('content')</main>
            </div>
        </div>
    @else
        <div class="institution-bar">
            <div class="container"><span>Universitas Mitra Bangsa</span><span>Lembaga Penelitian dan Pengabdian kepada
                    Masyarakat</span></div>
        </div>
        <div class="public-header-wrap">
            <header class="public-header container">
                <a class="brand" href="{{ route('home') }}"><x-brand subtitle="Repositori akademik" /></a>
                <button class="icon-button public-menu-toggle" type="button" aria-label="Buka menu navigasi"
                    aria-controls="public-navigation" aria-expanded="false" data-public-toggle><x-icon
                        name="menu" /></button>
                <x-theme-picker />
                <nav id="public-navigation" aria-label="Navigasi publik" data-public-menu>
                    <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Beranda</a>
                    <a @if (request()->routeIs('repository.search')) aria-current="page" @endif
                        href="{{ route('repository.search') }}">Jelajahi karya</a>
                    <a @if (request()->routeIs('repository.lecturers', 'repository.lecturer')) aria-current="page" @endif
                        href="{{ route('repository.lecturers') }}">Dosen</a>
                    <a href="{{ route('home') }}#panduan">Panduan</a>
                    @auth
                        <a class="button button-small" href="{{ route('admin.dashboard') }}">Ruang kerja<x-icon
                                name="arrow-right" size="17" /></a>
                    @else
                        <a class="button button-small" href="{{ route('login') }}">Masuk<x-icon name="arrow-right"
                                size="17" /></a>
                    @endauth
                </nav>
            </header>
        </div>
        <main id="main" tabindex="-1">@include('partials.feedback') @yield('content')</main>
        <footer class="public-footer">
            <div class="container footer-grid">
                <a class="brand" href="{{ route('home') }}"><x-brand /></a>
                <p>Ruang bersama untuk karya dan<br>pengetahuan Universitas Mitra Bangsa.</p>
                <a class="arrow-link" href="{{ route('repository.search') }}">Jelajahi repositori<x-icon
                        name="arrow-up-right" size="18" /></a>
            </div>
            <div class="container footer-bottom"><span>© {{ date('Y') }} LPPM UMIBA</span><span>One Single Data
                    Journal</span></div>
        </footer>
    @endif
</body>

</html>

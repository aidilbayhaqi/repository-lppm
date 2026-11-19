<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Akun · OSDJ LPPM Universitas Mitra Bangsa</title>
    @include('partials.theme-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <!-- UMIBA-BRANDING-FAVICONS-v1 -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
</head>

<body class="auth-page">
    <a class="skip-link" href="#main">Langsung ke konten</a>
    <header class="auth-header"><a class="brand" href="{{ route('home') }}"><x-brand /></a><x-theme-picker /><a
            class="arrow-link" href="{{ route('home') }}">Kembali ke repositori<x-icon name="arrow-up-right"
                size="17" /></a></header>
    <main id="main" class="auth-main" tabindex="-1">
        <div class="auth-card">{{ $slot }}</div>
    </main>
    <footer class="auth-footer">© {{ date('Y') }} LPPM Universitas Mitra Bangsa</footer>
</body>

</html>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · OSDJ</title>@include('partials.theme-head')@vite(['resources/css/app.css', 'resources/js/app.js'])
    <!-- UMIBA-BRANDING-FAVICONS-v1 -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
</head>

<body class="error-page">
    <main class="error-card"><a class="eyebrow" href="{{ url('/') }}">OSDJ · LPPM UMIBA</a>
        <p class="error-code">@yield('code')</p>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="error-actions"><a class="button" href="{{ url('/') }}">Ke beranda</a><a
                class="button button-secondary" href="{{ url('/login') }}">Masuk kembali</a></div>
        <p class="helper">Jika masalah berulang, sampaikan waktu kejadian dan alamat halaman kepada pengelola.</p>
    </main>
</body>

</html>

<x-guest-layout>
    <h1>Mulai berkontribusi</h1>
    <p>Buat akun untuk mengajukan karya akademik.</p>@include('partials.feedback')<form method="POST"
        action="{{ route('register') }}">@csrf<div class="form-field"><label for="nama">Nama lengkap</label><input
                id="nama" name="nama" value="{{ old('nama') }}" required autofocus autocomplete="name"></div>
        <div class="form-field mt-4"><label for="email">Email</label><input id="email" name="email"
                type="email" value="{{ old('email') }}" required autocomplete="username"></div>
        <div class="form-field mt-4">
            <label for="password">Password (minimal 12 karakter)</label><input id="password"
                name="password" type="password" required autocomplete="new-password"></div>
        <div class="form-field mt-4"><label for="password_confirmation">Konfirmasi password</label><input
                id="password_confirmation" name="password_confirmation" type="password" required
                autocomplete="new-password"></div>
        <p class="helper">Pendaftaran membuat akun mahasiswa. Peran lain serta profil penulis dapat dihubungkan oleh
            pengelola setelah verifikasi.</p><button class="button full" style="margin-top:20px">Buat akun ↗</button>
        <p class="helper" style="text-align:center">Sudah punya akun? <a class="arrow-link"
                href="{{ route('login') }}">Masuk</a></p>
    </form>
</x-guest-layout>

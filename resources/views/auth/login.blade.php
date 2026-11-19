<x-guest-layout>
    <h1>Masuk ke ruang kerja</h1>
    <p>Selamat datang kembali di repositori LPPM.</p><x-auth-session-status class="mb-4"
        :status="session('status')" />@include('partials.feedback')<form method="POST" action="{{ route('login') }}">@csrf<div
            class="form-field"><label for="email">Email</label><input id="email" type="email" name="email"
                value="{{ old('email') }}" required autofocus autocomplete="username"></div>
        <div class="form-field mt-4"><label for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password">
        </div>
        <div class="toolbar"><label class="remember-option" for="remember"><input type="checkbox" id="remember" name="remember" value="1" @checked(old('remember'))> Remember me</label><a
                href="{{ route('password.request') }}">Lupa password?</a></div><button class="button full"
            type="submit">Masuk <x-icon name="arrow-right" size="18" /></button>
        @if (config('repository.registration_enabled'))
            <p class="helper text-center">Belum punya akun? <a class="arrow-link"
                    href="{{ route('register') }}">Daftar</a></p>
        @endif
    </form>
</x-guest-layout>

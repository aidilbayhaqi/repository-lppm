<x-guest-layout>
    <div class="mx-auto w-full max-w-xl rounded-2xl border border-[var(--line)] bg-[var(--paper)] p-6 sm:p-8">
        <h1 class="text-2xl font-semibold text-[var(--ink)]">Keamanan dua faktor</h1>
        @if (!$configured)
            <p class="mt-3 text-sm text-[var(--muted)]">Masukkan secret di aplikasi authenticator (TOTP, 6 digit), lalu verifikasi dengan password akun.</p>
            <div class="mt-5 rounded-xl border border-[var(--line)] bg-[var(--surface)] p-4">
                <p class="text-xs font-semibold">Secret autentikator — simpan secara privat</p>
                <p class="mt-2 break-all font-mono text-sm">{{ $secret }}</p>
                <p class="mt-3 break-all text-xs text-[var(--muted)]">URI: {{ $provisioningUri }}</p>
            </div>
            <form method="post" action="{{ route('mfa.enroll') }}" class="mt-6 space-y-4">
                @csrf
                <div><label for="setup_password" class="block text-sm">Password saat ini</label><input id="setup_password" class="mt-2 w-full" type="password" autocomplete="current-password" name="current_password" required><x-input-error :messages="$errors->get('current_password')" /></div>
                <div><label for="setup_code" class="block text-sm">Kode 6 digit</label><input id="setup_code" class="mt-2 w-full" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" required><x-input-error :messages="$errors->get('code')" /></div>
                <button class="button w-full" type="submit">Aktifkan keamanan dua faktor</button>
            </form>
        @elseif (!$verified)
            <p class="mt-3 text-sm text-[var(--muted)]">Masukkan kode dari authenticator, atau recovery code yang belum digunakan.</p>
            <form method="post" action="{{ route('mfa.verify') }}" class="mt-5 space-y-4">
                @csrf
                <div><label for="code" class="block text-sm">Kode authenticator / recovery</label><input id="code" class="mt-2 w-full" name="code" autocomplete="one-time-code" required maxlength="64" autofocus><x-input-error :messages="$errors->get('code')" /></div>
                <button class="button w-full" type="submit">Verifikasi dan lanjutkan</button>
            </form>
        @else
            <p class="mt-3 text-sm text-[var(--muted)]">MFA aktif dan sesi saat ini sudah diverifikasi.</p>
            <form method="post" action="{{ route('mfa.disable') }}" class="mt-5 space-y-4">
                @csrf
                <p class="text-sm font-medium">Nonaktifkan MFA (memerlukan password dan kode authenticator baru)</p>
                <input name="current_password" type="password" class="w-full" autocomplete="current-password" placeholder="Password saat ini" required>
                <input name="code" class="w-full" maxlength="64" placeholder="Kode TOTP 6 digit" required>
                <x-input-error :messages="$errors->get('current_password')" />
                <x-input-error :messages="$errors->get('code')" />
                <button class="button button-outline" type="submit">Nonaktifkan MFA</button>
            </form>
            <a class="mt-4 inline-block text-sm underline" href="{{ route('profile.edit') }}">Kembali ke profil</a>
        @endif
        <form action="{{ route('logout') }}" method="post" class="mt-6">@csrf <button class="text-sm underline" type="submit">Keluar</button></form>
    </div>
</x-guest-layout>

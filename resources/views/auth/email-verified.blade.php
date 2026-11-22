<x-guest-layout>
    <div class="mx-auto w-full max-w-xl text-center">
        <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full border border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-8 w-8">
                <path d="M20 6 9 17l-5-5" />
            </svg>
        </div>

        <p class="text-sm font-semibold tracking-wide text-[var(--muted)]">Verifikasi email</p>
        <h1 class="mt-3 text-2xl font-semibold tracking-tight text-[var(--ink)] sm:text-3xl">
            Email berhasil diverifikasi
        </h1>
        <p class="mt-4 text-sm leading-7 text-[var(--muted)]">
            Alamat email akun Anda sudah dikonfirmasi. Silakan masuk dengan email dan
            password untuk melanjutkan ke ruang kerja repositori.
        </p>

        <div class="mt-6 rounded-xl border border-[var(--line)] bg-[var(--surface)] px-4 py-3 text-left">
            <p class="text-sm leading-6 text-[var(--muted)]">
                Verifikasi email memastikan kepemilikan alamat email.
                Persetujuan akun dan penetapan hak akses tetap dikelola oleh Superadmin.
            </p>
        </div>

        <a href="{{ route('login') }}" class="button full mt-7 inline-flex items-center justify-center gap-2">
            Lanjut ke halaman login
            <x-icon name="arrow-right" size="18" />
        </a>

        <a href="{{ route('home') }}" class="mt-4 inline-block text-sm font-medium text-[var(--muted)] underline underline-offset-4 hover:text-[var(--ink)]">
            Kembali ke repositori
        </a>
    </div>
</x-guest-layout>

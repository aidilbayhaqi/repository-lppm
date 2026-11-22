<x-guest-layout>
    <div class="mx-auto w-full max-w-xl rounded-2xl border border-[var(--line)] bg-[var(--paper)] p-6">
        <h1 class="text-2xl font-semibold">Recovery codes</h1>
        <p class="mt-3 text-sm">Simpan kode ini di password manager. Setiap kode hanya berlaku satu kali dan tidak akan ditampilkan ulang.</p>
        <ul class="mt-5 grid grid-cols-2 gap-2 font-mono text-sm">
            @foreach ($codes as $code)
                <li class="rounded-lg border border-[var(--line)] p-3">{{ $code }}</li>
            @endforeach
        </ul>
        <a href="{{ route('dashboard') }}" class="button mt-6">Lanjutkan ke dashboard</a>
    </div>
</x-guest-layout>

<x-guest-layout>
    <h1>Daftar akun repositori</h1>
    <p>Lengkapi data diri. Akses diberikan setelah identitas disetujui Superadmin.</p>
    @include('partials.feedback')

    <form method="POST" action="{{ route('register') }}">
        @csrf
        <div class="form-field">
            <label for="nama">Nama lengkap</label>
            <input id="nama" name="nama" value="{{ old('nama') }}" maxlength="150" required autofocus autocomplete="name">
            @error('nama') <p class="helper text-danger">{{ $message }}</p> @enderror
        </div>
        <div class="form-field mt-4">
            <label for="email">Email aktif</label>
            <input id="email" name="email" type="email" maxlength="255" value="{{ old('email') }}" required autocomplete="email">
            @error('email') <p class="helper text-danger">{{ $message }}</p> @enderror
        </div>
        <div class="form-field mt-4">
            <label for="requested_role">Daftar sebagai</label>
            <select id="requested_role" name="requested_role" required>
                <option value="">— Pilih kategori —</option>
                <option value="mahasiswa" @selected(old('requested_role') === 'mahasiswa')>Mahasiswa</option>
                <option value="dosen" @selected(old('requested_role') === 'dosen')>Dosen</option>
                <option value="peneliti_eksternal" @selected(old('requested_role') === 'peneliti_eksternal')>Penulis / peneliti eksternal</option>
            </select>
            <p class="helper">Pilihan ini masih harus diverifikasi. Anda tidak dapat memilih peran pengelola.</p>
            @error('requested_role') <p class="helper text-danger">{{ $message }}</p> @enderror
        </div>
        <div class="form-field mt-4">
            <label for="identifier">NIM (mahasiswa) / NIDN (dosen) / identitas (penulis)</label>
            <input id="identifier" name="identifier" maxlength="100" value="{{ old('identifier') }}" autocomplete="off">
            <p class="helper">Wajib untuk mahasiswa dan dosen; opsional bagi penulis eksternal.</p>
            @error('identifier') <p class="helper text-danger">{{ $message }}</p> @enderror
        </div>
        <div class="form-field mt-4">
            <label for="program_studi_id">Program studi (khusus dosen)</label>
            <select name="program_studi_id" id="program_studi_id">
                <option value="">— Pilih referensi program studi —</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id }}" @selected((string)old('program_studi_id') === (string)$program->id)>{{ $program->nama }} · {{ $program->fakultas?->nama_fakultas }}</option>
                @endforeach
            </select>
            <p class="helper">Wajib untuk dosen. Fakultas dan program studi hanya berasal dari data referensi aktif.</p>
            @error('program_studi_id') <p class="helper text-danger">{{ $message }}</p> @enderror
        </div>
        <div class="form-field mt-4">
            <label for="password">Password (minimal 12 karakter)</label>
            <input id="password" name="password" type="password" required autocomplete="new-password">
            @error('password') <p class="helper text-danger">{{ $message }}</p> @enderror
        </div>
        <div class="form-field mt-4">
            <label for="password_confirmation">Konfirmasi password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
        </div>
        <button class="button full mt-4" type="submit">Kirim pendaftaran</button>
        <p class="helper text-center mt-4">Sudah punya akun? <a class="arrow-link" href="{{ route('login') }}">Masuk</a></p>
    </form>
</x-guest-layout>

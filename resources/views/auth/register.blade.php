<x-guest-layout>
    <div class="w-full max-w-6xl">
        <div class="mb-8 max-w-2xl">
            <p class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-[var(--blue)]">Akses repositori akademik</p>
            <h1 class="text-3xl font-semibold tracking-tight text-[var(--ink)] sm:text-4xl">Buat akun repositori</h1>
            <p class="mt-3 max-w-xl text-sm leading-7 text-[var(--muted)]">
                Lengkapi identitas dan keamanan akun. Setiap permohonan akan diperiksa Superadmin sebelum akses diberikan.
            </p>
        </div>

        @include('partials.feedback')

        <form method="POST" action="{{ route('register') }}" data-registration-form class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 items-stretch gap-6 lg:grid-cols-2">
                <section aria-labelledby="registration-identity-title"
                    class="min-w-0 rounded-2xl border border-[var(--line)] bg-[var(--paper)] p-5 shadow-sm transition-shadow duration-200 hover:shadow-md sm:p-8">
                    <div class="mb-7 flex items-start gap-4 border-b border-[var(--line)] pb-6">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[var(--blue-soft)] text-base font-semibold text-[var(--blue)]" aria-hidden="true">01</span>
                        <div>
                            <h2 id="registration-identity-title" class="text-xl font-semibold text-[var(--ink)]">Identitas pendaftar</h2>
                            <p class="mt-1 text-sm text-[var(--muted)]">Data dasar untuk proses pemeriksaan akun.</p>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <div class="form-field">
                            <label for="nama" class="mb-2 block text-sm font-medium">Nama lengkap <span aria-hidden="true" class="text-red-600">*</span></label>
                            <input class="w-full" id="nama" name="nama" value="{{ old('nama') }}" maxlength="150"
                                placeholder="Sesuai identitas akademik" required autofocus autocomplete="name" @error('nama') aria-invalid="true" aria-describedby="nama-error" @enderror>
                            @error('nama') <p id="nama-error" class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                        </div>

                        <div class="form-field">
                            <label for="email" class="mb-2 block text-sm font-medium">Email aktif <span aria-hidden="true" class="text-red-600">*</span></label>
                            <input class="w-full" id="email" name="email" type="email" maxlength="255" value="{{ old('email') }}"
                                placeholder="nama@contoh.ac.id" required autocomplete="email" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                            @error('email') <p id="email-error" class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                        </div>

                        <div class="form-field">
                            <label for="requested_role" class="mb-2 block text-sm font-medium">Daftar sebagai <span aria-hidden="true" class="text-red-600">*</span></label>
                            <select class="w-full" id="requested_role" name="requested_role" required
                                aria-describedby="requested-role-help" @error('requested_role') aria-invalid="true" @enderror>
                                <option value="">Pilih kategori atau peran</option>
                                @foreach ($roleOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('requested_role') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p id="requested-role-help" class="mt-2 text-xs leading-5 text-[var(--muted)]">
                                Peran ini hanya permohonan. Superadmin memverifikasi dan menetapkan akses akhir.
                            </p>
                            @error('requested_role') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                        </div>

                        <div class="form-field">
                            <label for="identifier" data-identifier-label class="mb-2 block text-sm font-medium">
                                Nomor identitas <span data-identifier-required aria-hidden="true" class="text-red-600" @if (! in_array(old('requested_role'), ['mahasiswa', 'dosen'], true)) hidden @endif>*</span>
                            </label>
                            <input class="w-full" id="identifier" name="identifier" maxlength="100" value="{{ old('identifier') }}"
                                placeholder="NIM, NIDN, atau identitas lainnya" autocomplete="off"
                                @if (in_array(old('requested_role'), ['mahasiswa', 'dosen'], true)) required @endif
                                aria-describedby="identifier-help" @error('identifier') aria-invalid="true" @enderror>
                            <p id="identifier-help" data-identifier-help class="mt-2 text-xs leading-5 text-[var(--muted)]">
                                Wajib untuk mahasiswa dan dosen; opsional untuk kategori lainnya.
                            </p>
                            @error('identifier') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                        </div>

                        <div class="form-field" data-program-field
                            @if (old('requested_role') !== 'dosen' && ! $errors->has('program_studi_id')) hidden @endif>
                            <label for="program_studi_id" class="mb-2 block text-sm font-medium">Program studi <span aria-hidden="true" class="text-red-600">*</span></label>
                            <select class="w-full" name="program_studi_id" id="program_studi_id"
                                @if (old('requested_role') !== 'dosen') disabled @else required @endif
                                @error('program_studi_id') aria-invalid="true" @enderror>
                                <option value="">Pilih program studi aktif</option>
                                @foreach ($programs as $program)
                                    <option value="{{ $program->id }}" @selected((string) old('program_studi_id') === (string) $program->id)>
                                        {{ $program->nama }} · {{ $program->fakultas?->nama_fakultas }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-2 text-xs leading-5 text-[var(--muted)]">Hanya data program studi dan fakultas aktif yang tersedia.</p>
                            @error('program_studi_id') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>

                <section aria-labelledby="registration-security-title"
                    class="flex min-w-0 flex-col rounded-2xl border border-[var(--line)] bg-[var(--paper)] p-5 shadow-sm transition-shadow duration-200 hover:shadow-md sm:p-8">
                    <div class="mb-7 flex items-start gap-4 border-b border-[var(--line)] pb-6">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[var(--blue-soft)] text-base font-semibold text-[var(--blue)]" aria-hidden="true">02</span>
                        <div>
                            <h2 id="registration-security-title" class="text-xl font-semibold text-[var(--ink)]">Keamanan akun</h2>
                            <p class="mt-1 text-sm text-[var(--muted)]">Siapkan kredensial dan tinjau proses aktivasi.</p>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <div class="form-field">
                            <label for="password" class="mb-2 block text-sm font-medium">Password <span aria-hidden="true" class="text-red-600">*</span></label>
                            <input class="w-full" id="password" name="password" type="password" required
                                minlength="12" autocomplete="new-password" placeholder="Minimal 12 karakter"
                                aria-describedby="password-help" @error('password') aria-invalid="true" @enderror>
                            <p id="password-help" class="mt-2 text-xs leading-5 text-[var(--muted)]">Gunakan password unik, minimal 12 karakter.</p>
                            @error('password') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                        </div>

                        <div class="form-field">
                            <label for="password_confirmation" class="mb-2 block text-sm font-medium">Konfirmasi password <span aria-hidden="true" class="text-red-600">*</span></label>
                            <input class="w-full" id="password_confirmation" name="password_confirmation" type="password"
                                required autocomplete="new-password" placeholder="Ulangi password">
                        </div>

                        <div class="rounded-xl border border-[var(--line)] bg-[var(--surface)] p-4">
                            <p class="text-sm font-semibold text-[var(--ink)]">Setelah mengirim pendaftaran</p>
                            <ol class="mt-3 space-y-3 text-xs leading-6 text-[var(--muted)]">
                                <li class="flex gap-3"><span class="font-bold text-[var(--blue)]">1.</span><span>Permohonan masuk ke antrean Superadmin.</span></li>
                                <li class="flex gap-3"><span class="font-bold text-[var(--blue)]">2.</span><span>Identitas dan peran ditinjau sebelum akses diberikan.</span></li>
                                <li class="flex gap-3"><span class="font-bold text-[var(--blue)]">3.</span><span>Setelah disetujui, verifikasi email jika diminta, lalu masuk.</span></li>
                            </ol>
                        </div>
                    </div>

                    <div class="mt-auto pt-7">
                        <button class="button w-full" type="submit">Kirim permohonan akun <span aria-hidden="true">→</span></button>
                        <p class="mt-4 text-center text-sm text-[var(--muted)]">
                            Sudah punya akun? <a class="font-semibold text-[var(--blue)] underline-offset-4 hover:underline" href="{{ route('login') }}">Masuk di sini</a>
                        </p>
                    </div>
                </section>
            </div>
            <p class="text-center text-xs text-[var(--muted)]">Data pendaftaran digunakan untuk pemeriksaan akses repositori akademik.</p>
        </form>
    </div>
</x-guest-layout>

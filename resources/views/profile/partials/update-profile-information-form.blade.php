<section>
    <header>
        <h2 class="!text-lg text-[var(--ink)]">Informasi akun</h2>
        <p class="mt-2 text-sm">Informasi ini digunakan untuk identitas dan komunikasi akun.</p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">@csrf</form>
    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-5">
        @csrf @method('patch')
        <input type="hidden" name="lock_version" value="{{ $user->lock_version }}">
        <div>
            <x-input-label for="nama" value="Nama lengkap" />
            <x-text-input id="nama" name="nama" type="text" class="mt-2 block w-full" :value="old('nama', $user->nama)" required autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('nama')" />
        </div>
        <div>
            <x-input-label for="jabatan" value="Jabatan / posisi (opsional)" />
            <x-text-input id="jabatan" name="jabatan" type="text" class="mt-2 block w-full" :value="old('jabatan', $user->jabatan)" maxlength="120" placeholder="Contoh: Dosen Sistem Informasi" />
            <x-input-error class="mt-2" :messages="$errors->get('jabatan')" />
        </div>
        <div>
            <x-input-label for="bio" value="Bio singkat (opsional)" />
            <textarea id="bio" name="bio" rows="3" maxlength="600" placeholder="Bidang riset atau tanggung jawab" class="mt-2 block w-full rounded-lg border border-[var(--field-line)] bg-[var(--paper)] px-4 py-3 text-sm text-[var(--ink)]">{{ old('bio', $user->bio) }}</textarea>
            <x-input-error class="mt-2" :messages="$errors->get('bio')" />
        </div>
        <div>
            <x-input-label for="email" value="Alamat email" />
            <x-text-input id="email" name="email" type="email" class="mt-2 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />
            @unless($user->hasVerifiedEmail())
                <p class="mt-2 text-sm">Email belum diverifikasi.
                    <button form="send-verification" type="submit" class="font-medium text-[var(--blue)] underline underline-offset-4">Kirim ulang verifikasi</button>
                </p>
            @endunless
        </div>
        <div>
            <x-input-label for="profile_current_password" value="Password saat ini (jika mengganti email)" />
            <x-text-input id="profile_current_password" name="current_password" type="password" class="mt-2 block w-full" autocomplete="current-password" />
            <x-input-error class="mt-2" :messages="$errors->get('current_password')" />
        </div>
        <div class="flex flex-wrap items-center gap-4">
            <x-primary-button>Simpan profil</x-primary-button>
            @if(session('status') === 'profile-updated')<span role="status" class="text-sm text-emerald-700">Perubahan tersimpan.</span>@endif
        </div>
    </form>
</section>

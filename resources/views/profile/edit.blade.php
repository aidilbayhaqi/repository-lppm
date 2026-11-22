<x-app-layout>
    <div class="page-heading">
        <div>
            <div class="eyebrow">Pengaturan akun</div>
            <h1>Profil dan keamanan</h1>
            <p>Kelola identitas akun dan akses masuk.</p>
        </div>
    </div>

    <div class="mb-6 flex flex-wrap items-center gap-5 rounded-xl border border-[var(--line)] bg-[var(--paper)] p-5 sm:p-6">
        <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-[var(--line)] bg-[var(--surface)] text-2xl font-semibold text-[var(--ink)]">
            @if($user->profile_photo_path)
                <img src="{{ route('profile.photo.show') }}" alt="Foto profil" class="h-full w-full object-cover" width="80" height="80">
            @else
                <span aria-hidden="true">{{ mb_strtoupper(mb_substr($user->nama, 0, 1)) }}</span>
            @endif
        </div>
        <div class="min-w-0 flex-1">
            <h2 class="!text-xl text-[var(--ink)]">{{ $user->nama }}</h2>
            <p class="mt-1 break-all text-sm text-[var(--muted)]">{{ $user->email }}</p>
            <div class="mt-2 flex flex-wrap gap-2 text-xs">
                <span class="rounded-full border border-[var(--line)] px-3 py-1 text-[var(--ink)]">{{ ucfirst(str_replace('_',' ',$user->role)) }}</span>
                @if($user->hasVerifiedEmail())
                    <span class="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-[var(--teal)]">Email terverifikasi</span>
                @else
                    <span class="rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-[var(--amber)]">Email belum diverifikasi</span>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1.5fr)_minmax(300px,1fr)]">
        <div class="min-w-0 space-y-5">
            <div class="panel">@include('profile.partials.update-profile-information-form')</div>
            <div class="panel">@include('profile.partials.update-password-form')</div>
            <div class="panel"><h2 class="!text-lg">Autentikasi dua faktor</h2><p class="mt-2 text-sm">{{ $user->mfa_secret ? 'TOTP aktif. Simpan kode pemulihan secara aman.' : 'Gunakan aplikasi authenticator untuk mengamankan akun.' }}</p><a class="button mt-4" href="{{ route('mfa.challenge') }}">{{ $user->mfa_secret ? 'Kelola atau verifikasi MFA' : 'Aktifkan MFA' }}</a></div>
        </div>
        <div class="min-w-0 space-y-5">
            <section class="panel" aria-labelledby="profile-photo-heading">
                <h2 id="profile-photo-heading" class="!text-lg">Foto profil</h2>
                <p class="mt-2 text-sm">Gunakan JPG atau PNG, maksimal 2 MB.</p>
                <form action="{{ route('profile.photo.store') }}" method="post" enctype="multipart/form-data" class="mt-5 space-y-4">
                    @csrf
                    <input name="lock_version" type="hidden" value="{{ $user->lock_version }}">
                    <div><label class="mb-2 block font-medium text-[var(--ink)]" for="profile-photo">Pilih foto</label>
                        <input id="profile-photo" name="photo" type="file" accept="image/png,image/jpeg" required class="block w-full rounded-lg border border-[var(--field-line)] p-3 text-sm text-[var(--ink)] file:mr-3 file:rounded-md file:border-0 file:bg-[var(--blue-soft)] file:px-3 file:py-2 file:font-medium file:text-[var(--ink)]">
                        <x-input-error :messages="$errors->get('photo')" class="mt-2" />
                    </div>
                    <button type="submit" class="button transition-transform duration-150 hover:-translate-y-0.5 active:translate-y-0 motion-reduce:transform-none">Unggah foto</button>
                </form>
                @if($user->profile_photo_path)
                    <form action="{{ route('profile.photo.destroy') }}" method="post" class="mt-3">
                        @csrf @method('delete')
                        <input name="lock_version" type="hidden" value="{{ $user->lock_version }}">
                        <button type="submit" class="text-sm font-medium text-[var(--muted)] underline underline-offset-4 hover:text-[var(--ink)]">Hapus foto</button>
                    </form>
                @endif
            </section>
            <div class="panel">@include('profile.partials.delete-user-form')</div>
        </div>
    </div>
</x-app-layout>

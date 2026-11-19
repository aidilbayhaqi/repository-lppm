<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Hapus akun') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Penghapusan akun tidak dapat dibatalkan. Simpan informasi yang masih Anda perlukan sebelum melanjutkan.') }}
        </p>
    </header>

    <x-danger-button
        type="button" data-dialog-open="confirm-user-deletion"
    >{{ __('Hapus akun') }}</x-danger-button>

    <dialog id="confirm-user-deletion" class="account-dialog" aria-labelledby="delete-account-title" data-open-on-load="{{ $errors->userDeletion->isNotEmpty() ? 'true' : 'false' }}">
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 id="delete-account-title" class="text-lg font-medium text-gray-900">
                {{ __('Hapus akun Anda?') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                {{ __('Tindakan ini tidak dapat dibatalkan. Masukkan password untuk mengonfirmasi penghapusan akun.') }}
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="{{ __('Password') }}" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4"
                    placeholder="{{ __('Password') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button data-dialog-close>
                    {{ __('Batal') }}
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    {{ __('Hapus akun') }}
                </x-danger-button>
            </div>
        </form>
    </dialog>
</section>

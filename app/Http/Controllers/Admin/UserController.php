<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\AuditLog;
use App\Models\Penulis;
use App\Models\User;
use App\Support\LookupOptions;
use App\Support\OptimisticLock;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage-users');

        return view('admin.users.index', ['users' => User::select(['id', 'nama', 'email', 'role', 'is_active'])->orderBy('nama')->orderBy('id')->paginate(20)]);
    }

    public function create(): View
    {
        return $this->form(new User);
    }

    public function edit(User $user): View
    {
        return $this->form($user);
    }

    private function form(User $user): View
    {
        Gate::authorize('manage-users');

        return view('admin.users.form', ['record' => $user, 'authors' => LookupOptions::initial(Penulis::where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id))->select(['id', 'nama']), 'nama', [old('penulis_id', $user->penulis?->id)])]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        return $this->save($request);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        return $this->save($request, $user->id);
    }

    private function save(UserRequest $request, ?int $id = null): RedirectResponse
    {
        DB::transaction(function () use ($request, $id) {
            User::where('role', 'admin')->orderBy('id')->lockForUpdate()->get();
            Gate::forUser($request->user()->fresh())->authorize('manage-users');
            $user = $id ? User::lockForUpdate()->findOrFail($id) : new User;
            $data = $request->validated();
            if ($id && $user->registrationRequest && $user->registrationRequest->status !== 'approved') {
                throw ValidationException::withMessages(['role' => 'Pendaftaran belum disetujui. Gunakan halaman Persetujuan akun.']);
            }
            if ($id) {
                OptimisticLock::check($user, (int) $data['lock_version']);
            }
            if ($id === $request->user()->id && ($data['role'] !== $user->role || ! $data['is_active'])) {
                throw ValidationException::withMessages(['role' => 'Peran dan status akun sendiri harus diubah admin lain.']);
            }
            $old = $user->only(['nama', 'email', 'role', 'is_active']);
            if (empty($data['password'])) {
                unset($data['password']);
            }
            $user->fill(Arr::except($data, ['penulis_id', 'lock_version']));
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
            $user->save();
            if ($data['penulis_id'] ?? null) {
                $author = Penulis::lockForUpdate()->findOrFail($data['penulis_id']);
                if ($author->user_id && $author->user_id !== $user->id) {
                    throw ValidationException::withMessages(['penulis_id' => 'Penulis sudah terhubung ke akun lain.']);
                }
                Penulis::where('user_id', $user->id)->where('id', '!=', $author->id)->update(['user_id' => null]);
                $author->update(['user_id' => $user->id]);
            } else {
                Penulis::where('user_id', $user->id)->update(['user_id' => null]);
            }
            if (! $id) {
                event(new Registered($user));
            }
            AuditLog::record($user, $request->user(), $id ? 'user_updated' : 'user_created', $old, $user->only(['nama', 'email', 'role', 'is_active']));
        });

        return redirect()->route('admin.users.index')->with('success', 'Akun berhasil disimpan.');
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ProgramStudi;
use App\Models\RegistrationRequest;
use App\Models\User;
use App\Services\RegistrationApprovalService;
use App\Support\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        abort_unless(config('repository.registration_enabled'), 404);

        return view('auth.register', [
            'roleOptions' => RegistrationApprovalService::ROLE_LABELS,
            'programs' => ProgramStudi::query()
                ->where('aktif', true)
                ->whereHas('fakultas', fn ($q) => $q->where('aktif', true))
                ->with('fakultas:id,nama_fakultas')
                ->orderBy('nama')
                ->get(['id', 'nama', 'fakultas_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(config('repository.registration_enabled'), 404);

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', ...PasswordPolicy::rules()],
            'requested_role' => ['required', Rule::in(RegistrationApprovalService::ASSIGNABLE_ROLES)],
            'identifier' => ['nullable', 'string', 'max:100',
                Rule::requiredIf(fn () => in_array($request->input('requested_role'), ['dosen', 'mahasiswa'], true))],
            'program_studi_id' => [
                Rule::requiredIf(fn () => $request->input('requested_role') === 'dosen'),
                'nullable', 'integer',
                Rule::exists('program_studi', 'id')->where('aktif', true),
            ],
        ]);

        // Never create a user with elevated privileges or an active session
        // based on unauthenticated registration input.
        if (($data['requested_role'] ?? '') === 'dosen') {
            $validProgram = ProgramStudi::whereKey($data['program_studi_id'] ?? null)
                ->where('aktif', true)
                ->whereHas('fakultas', fn ($q) => $q->where('aktif', true))
                ->exists();
            if (! $validProgram) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'program_studi_id' => 'Program studi harus berasal dari referensi aktif.',
                ]);
            }
        }

        DB::transaction(function () use ($data): void {
            $user = User::create([
                'nama' => trim($data['nama']),
                'email' => mb_strtolower(trim($data['email'])),
                'password' => Hash::make($data['password']),
                'role' => User::ROLE_MAHASISWA,
                'is_active' => false,
            ]);

            RegistrationRequest::create([
                'user_id' => $user->id,
                'requested_role' => $data['requested_role'],
                'identifier' => trim($data['identifier'] ?? '') ?: null,
                'program_studi_id' => $data['requested_role'] === 'dosen'
                    ? $data['program_studi_id'] : null,
                'status' => 'pending',
            ]);
        });

        // No Auth::login or Registered event before superadmin approval.
        return redirect()->route('login')->with(
            'status',
            'Pendaftaran diterima. Akun menunggu persetujuan Superadmin sebelum dapat digunakan.'
        );
    }
}

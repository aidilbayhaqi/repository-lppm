<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Dosen;
use App\Models\Penulis;
use App\Models\ProgramStudi;
use App\Models\RegistrationRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RegistrationApprovalService
{
    public const ASSIGNABLE_ROLES = [
        'mahasiswa', 'dosen', 'peneliti_eksternal', 'operator', 'verifier', 'reviewer',
    ];

    public const ROLE_LABELS = [
        'mahasiswa' => 'Mahasiswa',
        'dosen' => 'Dosen',
        'peneliti_eksternal' => 'Penulis / peneliti eksternal',
        'operator' => 'Operator LPPM',
        'verifier' => 'Verifier publikasi',
        'reviewer' => 'Reviewer',
    ];

    public function approve(RegistrationRequest $request, User $actor, array $input): void
    {
        $approvedUser = DB::transaction(function () use ($request, $actor, $input): User {
            $approval = RegistrationRequest::query()->lockForUpdate()->findOrFail($request->id);
            $user = User::query()->lockForUpdate()->findOrFail($approval->user_id);
            $this->assertPending($approval, $user);

            if (! $actor->isAdmin() || ! in_array($input['role'], self::ASSIGNABLE_ROLES, true)) {
                abort(403);
            }

            $role = $input['role'];
            $identifier = trim((string) ($input['identifier'] ?? ''));
            $programId = $input['program_studi_id'] ?? null;
            $author = null;

            if (in_array($role, ['mahasiswa', 'dosen'], true) && $identifier === '') {
                $this->invalid('identifier', 'NIM atau NIDN wajib diisi untuk peran ini.');
            }

            if ($role === 'dosen') {
                $program = ProgramStudi::query()
                    ->whereKey($programId)
                    ->where('aktif', true)
                    ->whereHas('fakultas', fn ($q) => $q->where('aktif', true))
                    ->first();

                if (! $program) {
                    $this->invalid('program_studi_id', 'Pilih program studi dari referensi aktif.');
                }

                // A matching NIDN is never sufficient to claim another user's profile:
                // admin must confirm existing reference ownership.
                $dosen = Dosen::query()->where('nidn', $identifier)->lockForUpdate()->first();
                if ($dosen) {
                    if (! $dosen->aktif || self::normalize($dosen->nama) !== self::normalize($user->nama)
                        || (int) $dosen->program_studi_id !== (int) $program->id) {
                        $this->invalid('identifier', 'NIDN sudah terdaftar dengan identitas atau Prodi berbeda.');
                    }
                    $this->requireConfirmation($input);
                } else {
                    $dosen = Dosen::create([
                        'nama' => $user->nama,
                        'email' => $user->email,
                        'nidn' => $identifier,
                        'program_studi_id' => $program->id,
                        'aktif' => true,
                    ]);
                    AuditLog::record($dosen, $actor, 'reference_created', [], ['nama' => $dosen->nama], 'Disetujui melalui registrasi.');
                }

                $author = Penulis::query()->where('dosen_id', $dosen->id)->lockForUpdate()->first();
                if ($author) {
                    if (! $author->aktif || $author->jenis_penulis !== 'dosen'
                        || self::normalize($author->nama) !== self::normalize($user->nama)) {
                        $this->invalid('identifier', 'Profil penulis dosen memiliki data yang tidak cocok.');
                    }
                    $this->requireConfirmation($input);
                } else {
                    $author = Penulis::create([
                        'entry_key' => hash('sha256', 'dosen:'.$identifier),
                        'dosen_id' => $dosen->id,
                        'nama' => $user->nama,
                        'nama_normalisasi' => self::normalize($user->nama),
                        'jenis_penulis' => 'dosen',
                        'identitas' => $identifier,
                        'aktif' => true,
                    ]);
                    AuditLog::record($author, $actor, 'reference_created', [], ['nama' => $author->nama], 'Disetujui melalui registrasi.');
                }
            } elseif (in_array($role, ['mahasiswa', 'peneliti_eksternal'], true)) {
                $kind = $role === 'mahasiswa' ? 'mahasiswa' : 'eksternal';
                $normalized = self::normalize($user->nama);

                // Reuse an existing record only when the reviewer explicitly
                // acknowledges the identity and all identifying fields match.
                $author = $identifier !== ''
                    ? Penulis::query()->where('jenis_penulis', $kind)
                        ->where('identitas', $identifier)->lockForUpdate()->first()
                    : null;

                if ($author) {
                    if (! $author->aktif || self::normalize($author->nama) !== $normalized) {
                        $this->invalid('identifier', 'Identitas sudah digunakan oleh penulis dengan nama berbeda.');
                    }
                    $this->requireConfirmation($input);
                } else {
                    $key = $identifier !== ''
                        ? $kind.':'.$normalized.':'.self::normalize($identifier)
                        : $kind.':'.$normalized.':contributor:'.$user->id;

                    $author = Penulis::create([
                        'entry_key' => hash('sha256', $key),
                        'nama' => $user->nama,
                        'nama_normalisasi' => $normalized,
                        'jenis_penulis' => $kind,
                        'identitas' => $identifier ?: null,
                        'aktif' => true,
                    ]);
                    AuditLog::record($author, $actor, 'reference_created', [], ['nama' => $author->nama], 'Disetujui melalui registrasi.');
                }
            }

            if ($author) {
                if ($author->user_id && (int) $author->user_id !== (int) $user->id) {
                    $this->invalid('identifier', 'Referensi penulis sudah terhubung ke akun lain.');
                }
                $author->user_id = $user->id;
                $author->save();
            }

            $user->role = $role;
            $user->is_active = true;
            $user->save();

            $approval->update([
                'status' => 'approved',
                'identifier' => $identifier ?: null,
                'program_studi_id' => $role === 'dosen' ? $programId : null,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => trim((string) ($input['review_note'] ?? '')) ?: null,
            ]);

            AuditLog::record($user, $actor, 'registration_approved', [
                'status' => 'pending',
            ], [
                'status' => 'approved',
                'requested_role' => $approval->requested_role,
                'role' => $role,
            ]);

            return $user;
        });

        // Notify only approved accounts. Email verification is still required.
        event(new Registered($approvedUser));
    }

    public function reject(RegistrationRequest $request, User $actor, string $note): void
    {
        DB::transaction(function () use ($request, $actor, $note): void {
            $approval = RegistrationRequest::query()->lockForUpdate()->findOrFail($request->id);
            $user = User::query()->lockForUpdate()->findOrFail($approval->user_id);
            $this->assertPending($approval, $user);
            abort_unless($actor->isAdmin(), 403);

            $approval->update([
                'status' => 'rejected',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);
            AuditLog::record($user, $actor, 'registration_rejected', [
                'status' => 'pending',
            ], [
                'status' => 'rejected',
            ], $note);
        });
    }

    private function assertPending(RegistrationRequest $approval, User $user): void
    {
        if ($approval->status !== 'pending' || $user->is_active) {
            $this->invalid('approval', 'Pengajuan ini sudah diproses atau status akun berubah.');
        }
    }

    private function requireConfirmation(array $input): void
    {
        if (empty($input['confirm_existing_reference'])) {
            $this->invalid('confirm_existing_reference', 'Konfirmasi kepemilikan referensi yang sudah ada sebelum menyetujui.');
        }
    }

    private static function normalize(string $text): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($text)));
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}

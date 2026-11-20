<?php

namespace App\Policies;

use App\Models\Publikasi;
use App\Models\User;
use App\Support\WorkType;
use Illuminate\Database\Eloquent\Model;

class WorkPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isActive() ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin', 'operator', 'dosen', 'mahasiswa', 'peneliti_eksternal');
    }

    public function view(User $user, Model $work): bool
    {
        return $user->isStaff() || ($user->role === 'verifier' && $work instanceof Publikasi) || $work->created_by === $user->id || $work->{WorkType::authors($work)}()->where('penulis.user_id', $user->id)->exists();
    }

    public function owner(User $user, Model $work): bool
    {
        if ($user->isStaff() || $work->created_by === $user->id) {
            return true;
        }
        if ($work instanceof Publikasi) {
            return $work->penulis()->where('penulis.user_id', $user->id)->where(function ($q) {
                $q->where('penulis_publikasi.penulis_korespondensi', true)->orWhere('penulis_publikasi.peneliti_utama', true);
            })->exists();
        }

        return false;
    }

    public function update(User $user, Model $work): bool
    {
        return ! $user->hasRole('verifier', 'reviewer') && $this->owner($user, $work) && WorkType::editable($work);
    }

    public function delete(User $user, Model $work): bool
    {
        return $this->update($user, $work);
    }

    public function submit(User $user, Model $work): bool
    {
        return $work instanceof Publikasi && $this->update($user, $work);
    }

    public function verify(User $user, Model $work): bool
    {
        if (! $work instanceof Publikasi || $work->archived_at || ! in_array($work->repository_status, ['submitted', 'pending_verification'])) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }

        return $user->hasRole('verifier') && ! $this->verificationConflict($user, $work);
    }

    public function verificationConflict(User $user, Model $work): bool
    {
        return $work->created_by === $user->id || $work->{WorkType::authors($work)}()->where('penulis.user_id', $user->id)->exists();
    }

    public function publish(User $user, Model $work): bool
    {
        return $user->isStaff() && ! $work->archived_at && ($work instanceof Publikasi ? in_array($work->repository_status, ['verified', 'publish_failed']) : WorkType::editable($work));
    }

    public function unpublish(User $user, Model $work): bool
    {
        return $user->isStaff() && WorkType::published($work);
    }

    public function reopen(User $user, Model $work): bool
    {
        return $user->isStaff() && $work instanceof Publikasi && ! $work->archived_at && in_array($work->repository_status, ['verified', 'publish_failed']);
    }

    public function restore(User $user, Model $work): bool
    {
        return $user->isStaff() && (bool) $work->archived_at;
    }
}

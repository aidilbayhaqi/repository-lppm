<?php

namespace App\Models;

use App\Notifications\ResetAccountPassword;
use App\Notifications\VerifyAccountEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $auth_version
 * @property Carbon $created_at
 * @property string $email
 * @property Carbon $email_verified_at
 * @property int $id
 * @property bool $is_active
 * @property int $lock_version
 * @property int $mfa_last_step
 * @property array<int, mixed> $mfa_recovery_codes
 * @property string $mfa_secret
 * @property string $nama
 * @property string $password
 * @property Carbon $remember_expires_at
 * @property string $role
 * @property Carbon $updated_at
 * @property-read Penulis|null $penulis
 * @property-read RegistrationRequest|null $registrationRequest
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::updating(function (User $user): void {
            $user->lock_version = (int) $user->getOriginal('lock_version') + 1;
            if ($user->isDirty(['password', 'email', 'role', 'is_active', 'mfa_secret', 'mfa_recovery_codes'])) {
                $user->auth_version = (int) $user->getOriginal('auth_version') + 1;
                $user->remember_token = Str::random(60);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Role Constants
    |--------------------------------------------------------------------------
    */

    public const ROLE_VERIFIER = 'verifier';

    public const ROLE_REVIEWER = 'reviewer';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_OPERATOR = 'operator';

    public const ROLE_DOSEN = 'dosen';

    public const ROLE_MAHASISWA = 'mahasiswa';

    public const ROLE_PENELITI_EKSTERNAL = 'peneliti_eksternal';

    /*
    |--------------------------------------------------------------------------
    | Mass Assignment
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'nama',
        'jabatan',
        'bio',
        'email',
        'password',
        'role',
        'is_active',
    ];

    /*
    |--------------------------------------------------------------------------
    | Hidden Attributes
    |--------------------------------------------------------------------------
    */

    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
        'mfa_recovery_codes',
    ];

    /*
    |--------------------------------------------------------------------------
    | Attribute Casting
    |--------------------------------------------------------------------------
    */

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'auth_version' => 'integer',
            'remember_expires_at' => 'datetime',
            'lock_version' => 'integer',
            'mfa_secret' => 'encrypted',
            'mfa_recovery_codes' => 'array',
            'mfa_last_step' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Role Helpers
    |--------------------------------------------------------------------------
    */

    public function requiresMfa(): bool
    {
        return (bool) config('repository.mfa_enabled')
            && in_array($this->role, config('repository.mfa_roles', []), true);
    }

    public function isStaff(): bool
    {
        return $this->hasRole('admin', 'operator');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isOperator(): bool
    {
        return $this->role === self::ROLE_OPERATOR;
    }

    public function isReviewer(): bool
    {
        return $this->role === self::ROLE_REVIEWER;
    }

    public function isDosen(): bool
    {
        return $this->role === self::ROLE_DOSEN;
    }

    public function isMahasiswa(): bool
    {
        return $this->role === self::ROLE_MAHASISWA;
    }

    public function isPenelitiEksternal(): bool
    {
        return $this->role === self::ROLE_PENELITI_EKSTERNAL;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /*
    |--------------------------------------------------------------------------
    | Account Status
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->is_active;
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyAccountEmail);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetAccountPassword($token));
    }

    public function penulis(): HasOne
    {
        return $this->hasOne(Penulis::class);
    }

    public function registrationRequest(): HasOne
    {
        return $this->hasOne(RegistrationRequest::class);
    }
}

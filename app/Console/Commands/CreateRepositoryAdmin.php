<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Auth\Events\Registered;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreateRepositoryAdmin extends Command
{
    protected $signature = 'repository:create-admin {--email=} {--name=}';

    protected $description = 'Membuat akun admin baru tanpa password bawaan.';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Email admin');
        $name = $this->option('name') ?: $this->ask('Nama admin');
        $password = $this->secret('Password admin (minimal 12 karakter)');
        $confirm = $this->secret('Ulangi password');
        $validator = Validator::make(['nama' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirm], ['nama' => 'required|string|max:150', 'email' => 'required|email|lowercase|max:255|unique:users', 'password' => ['required', 'confirmed', ...PasswordPolicy::rules()]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        DB::transaction(function () use ($name, $email, $password) {
            $user = User::create(['nama' => $name, 'email' => $email, 'password' => $password, 'role' => 'admin', 'is_active' => true]);
            event(new Registered($user));
            AuditLog::record($user, null, 'admin_bootstrapped', [], ['nama' => $name, 'email' => $email, 'role' => 'admin']);
        });
        $this->info('Admin dibuat. Masuk, lalu verifikasi email sebelum mengelola repositori.');

        return self::SUCCESS;
    }
}

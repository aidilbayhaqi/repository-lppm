<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Akun demo hanya untuk local/testing. Gunakan repository:create-admin di production.');
        }
        foreach (['admin', 'operator', 'verifier', 'dosen', 'mahasiswa', 'peneliti_eksternal'] as $role) {
            User::firstOrCreate(['email' => $role.'@repository.test'], ['nama' => ucfirst(str_replace('_', ' ', $role)).' Demo', 'password' => env('DEMO_PASSWORD', 'DemoLppm!2026'), 'role' => $role, 'is_active' => true, 'email_verified_at' => now()]);
        }
    }
}

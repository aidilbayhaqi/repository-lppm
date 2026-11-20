<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expand the existing role enum without changing any existing account.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'operator', 'verifier', 'reviewer', 'dosen', 'mahasiswa', 'peneliti_eksternal') NOT NULL DEFAULT 'mahasiswa'");

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'operator', 'verifier', 'reviewer', 'dosen', 'mahasiswa', 'peneliti_eksternal'])
                ->default('mahasiswa')
                ->change();
        });
    }

    public function down(): void
    {
        if (DB::table('users')->whereIn('role', ['verifier', 'reviewer'])->exists()) {
            throw new RuntimeException('Peran verifier/reviewer masih dipakai; ubah peran akun secara aman sebelum rollback migration.');
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'operator', 'dosen', 'mahasiswa', 'peneliti_eksternal') NOT NULL DEFAULT 'mahasiswa'");

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'operator', 'dosen', 'mahasiswa', 'peneliti_eksternal'])
                ->default('mahasiswa')
                ->change();
        });
    }
};

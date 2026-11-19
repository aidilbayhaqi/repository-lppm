<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'operator', 'dosen', 'mahasiswa', 'peneliti_eksternal'])->default('mahasiswa')->change();
        });
    }

    public function down(): void
    {
        DB::table('users')->whereNotIn('role', ['admin', 'operator', 'dosen'])->update(['role' => 'dosen']);
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'operator', 'dosen'])->default('operator')->change();
        });
    }
};

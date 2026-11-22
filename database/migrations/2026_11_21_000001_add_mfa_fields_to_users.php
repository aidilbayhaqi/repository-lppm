<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('mfa_secret')->nullable();
            $table->json('mfa_recovery_codes')->nullable();
            $table->unsignedBigInteger('mfa_last_step')->nullable();
        });
    }

    public function down(): void
    {
        // Fail closed: never silently destroy active second-factor secrets.
        if (\Illuminate\Support\Facades\DB::table('users')->whereNotNull('mfa_secret')->exists()) {
            throw new \RuntimeException('MFA masih aktif. Rollback memerlukan prosedur pemulihan terkontrol.');
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['mfa_secret', 'mfa_recovery_codes', 'mfa_last_step']));
    }
};

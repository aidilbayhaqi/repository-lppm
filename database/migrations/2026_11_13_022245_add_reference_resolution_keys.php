<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['penulis', 'jurnal', 'penerbit'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->char('entry_key', 64)->nullable()->unique());
        }
        Schema::table('dosen', fn (Blueprint $table) => $table->unsignedBigInteger('program_studi_id')->nullable()->change());
        Schema::table('publikasi', function (Blueprint $table) {
            $table->string('isbn', 20)->nullable()->index();
            $table->string('issn_cetak', 20)->nullable();
            $table->string('issn_elektronik', 20)->nullable();
        });
    }

    public function down(): void
    {
        foreach (['penulis', 'jurnal', 'penerbit'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropUnique(['entry_key']);
                $table->dropColumn('entry_key');
            });
        }
        Schema::table('publikasi', function (Blueprint $table) {
            $table->dropIndex(['isbn']);
            $table->dropColumn(['isbn', 'issn_cetak', 'issn_elektronik']);
        });
        // Keep nullable affiliation: rollback must not discard lecturers without a program.
    }
};

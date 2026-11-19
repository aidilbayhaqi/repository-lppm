<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sitasi', function (Blueprint $table) {
            $table->id();

              $table->foreignId('publikasi_id')
            ->constrained('publikasi')
            ->cascadeOnUpdate()
            ->cascadeOnDelete();

        $table->year('tahun');

        $table->enum('sumber', [
            'google_scholar',
            'scopus',
            'sinta',
            'lainnya',
        ])->default('google_scholar');

        $table->enum('cakupan', [
            'umum',
            'nasional',
            'internasional',
        ])->default('umum');

        $table->unsignedInteger('jumlah_sitasi')->default(0);

        $table->timestamps();

        $table->unique([
            'publikasi_id',
            'tahun',
            'sumber',
            'cakupan',
        ]);

        $table->index('tahun');
        $table->index('sumber');
        $table->index('cakupan');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sitasi');
    }
};

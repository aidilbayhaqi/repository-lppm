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
        Schema::create('kekayaan_intelektual', function (Blueprint $table) {
            $table->id();

            $table->string('judul', 500);

            $table->string('jenis', 100);

            $table->year('tahun')->nullable();

            $table->date('tanggal_permohonan')->nullable();

            $table->string(
                'nomor_permohonan',
                100
            )->nullable();

            $table->date(
                'tanggal_pencatatan'
            )->nullable();

            $table->string(
                'nomor_pencatatan',
                100
            )->nullable();

            $table->string('periode', 50)->nullable();

            $table->string(
                'tautan_eksternal',
                500
            )->nullable();

            $table->text('keterangan')->nullable();

            $table->enum('status', [
                'draft',
                'dipublikasikan',
            ])->default('draft');

            $table->timestamps();

            $table->index('jenis');
            $table->index('tahun');
            $table->index('status');
            $table->index('nomor_permohonan');
            $table->index('nomor_pencatatan');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kekayaan_intelektual');
    }
};

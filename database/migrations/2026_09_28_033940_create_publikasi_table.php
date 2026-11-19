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
        Schema::create('publikasi', function (Blueprint $table) {
            $table->id();

                 $table->foreignId('jurnal_id')
            ->nullable()
            ->constrained('jurnal')
            ->nullOnDelete()
            ->cascadeOnUpdate();

        $table->string('judul', 500);

        $table->text('abstrak')->nullable();

        $table->string('doi', 150)->nullable();

        $table->year('tahun_publikasi')->nullable();

        $table->date('tanggal_publikasi')->nullable();

        $table->string('volume', 50)->nullable();
        $table->string('nomor', 50)->nullable();
        $table->string('halaman', 50)->nullable();

        $table->string('tautan_eksternal', 500)->nullable();
        $table->string('tautan_google_scholar', 500)->nullable();

        $table->string('spmi', 100)->nullable();

        $table->year('tahun_dasar')->nullable();

        $table->string('klasifikasi', 150)->nullable();

        $table->decimal('biaya', 15, 2)->nullable();

        $table->text('keterangan')->nullable();

        $table->enum('status', [
            'draft',
            'dipublikasikan',
        ])->default('draft');

        $table->timestamps();

        $table->index('jurnal_id');
        $table->index('tahun_publikasi');
        $table->index('tahun_dasar');
        $table->index('status');
        $table->index('klasifikasi');

        $table->unique('doi');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publikasi');
    }
};

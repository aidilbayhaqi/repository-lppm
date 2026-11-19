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
        Schema::create('pengindeksan_publikasi', function (Blueprint $table) {
            $table->id();

               $table->foreignId('publikasi_id')
        ->constrained('publikasi')
        ->cascadeOnUpdate()
        ->cascadeOnDelete();

    $table->foreignId('pengindeks_id')
        ->constrained('pengindeks')
        ->cascadeOnUpdate()
        ->restrictOnDelete();

    $table->foreignId('tingkat_pengindeksan_id')
        ->nullable()
        ->constrained('tingkat_pengindeksan')
        ->nullOnDelete()
        ->cascadeOnUpdate();

    $table->string('tautan_sumber', 500)->nullable();

    $table->string(
        'tautan_sumber_internasional',
        500
    )->nullable();

    $table->text('keterangan')->nullable();

    $table->boolean('final')->default(false);

    $table->date('tanggal_verifikasi')->nullable();

    $table->timestamps();

    $table->unique(
        [
            'publikasi_id',
            'pengindeks_id',
            'tingkat_pengindeksan_id',
        ],
        'uniq_pub_pengindeks_tingkat'
    );

    $table->index(
        'publikasi_id',
        'idx_pengindeksan_pub'
    );

    $table->index(
        'pengindeks_id',
        'idx_pengindeksan_indeks'
    );

    $table->index(
        'tingkat_pengindeksan_id',
        'idx_pengindeksan_tingkat'
    );
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengindeksan_publikasi');
    }
};

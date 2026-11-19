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
        Schema::create('buku', function (Blueprint $table) {
            $table->id();

               $table->foreignId('penerbit_id')
            ->nullable()
            ->constrained('penerbit')
            ->nullOnDelete()
            ->cascadeOnUpdate();

        $table->string('judul', 500);

        $table->string('isbn', 50)->nullable();

        $table->year('tahun_publikasi')->nullable();

        $table->date('tanggal_publikasi')->nullable();

        $table->unsignedInteger('jumlah_halaman')->nullable();

        $table->string('kategori', 100)->nullable();

        $table->string('tautan_eksternal', 500)->nullable();

        $table->text('keterangan')->nullable();

        $table->enum('status', [
            'draft',
            'dipublikasikan',
        ])->default('draft');

        $table->timestamps();

        $table->index('penerbit_id');
        $table->index('tahun_publikasi');
        $table->index('status');

        $table->unique('isbn');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buku');
    }
};

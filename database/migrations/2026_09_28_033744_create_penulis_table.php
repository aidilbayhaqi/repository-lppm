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
        Schema::create('penulis', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dosen_id')
                ->nullable()
                ->constrained('dosen')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('nama', 150);
            $table->string('nama_normalisasi', 150);

            $table->enum('jenis_penulis', [
                'dosen',
                'mahasiswa',
                'eksternal',
            ]);

            $table->string('identitas', 100)->nullable();

            $table->string('orcid_id', 50)->nullable();
            $table->string('scopus_id', 50)->nullable();
            $table->string('google_scholar_id', 100)->nullable();

            $table->boolean('aktif')->default(true);

            $table->index('nama_normalisasi');
            $table->index('jenis_penulis');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penulis');
    }
};

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
        Schema::create('dokumen', function (Blueprint $table) {
            $table->id();

            $table->string('pemilik_dokumen_type', 100);
            $table->unsignedBigInteger('pemilik_dokumen_id');

            $table->string('nama_file', 255);
            $table->string('nama_file_asli', 255)->nullable();

            $table->string('path_file', 500);

            $table->unsignedBigInteger('ukuran_file')->nullable();

            $table->string('mime_type', 100)->nullable();

            $table->enum('jenis_dokumen', [
                'dokumen_utama',
                'cover',
                'sertifikat',
                'lampiran',
                'pendukung',
                'lainnya',
            ])->default('dokumen_utama');

            $table->boolean('publik')->default(false);

            $table->timestamps();

            $table->index([
                'pemilik_dokumen_type',
                'pemilik_dokumen_id',
            ]);

            $table->index('jenis_dokumen');
            $table->index('publik');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dokumen');
    }
};

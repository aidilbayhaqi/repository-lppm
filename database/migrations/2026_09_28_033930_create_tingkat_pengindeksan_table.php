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
        Schema::create('tingkat_pengindeksan', function (Blueprint $table) {
            $table->id();

            $table->string('kode', 30)->unique();

            $table->string('nama', 150);

            $table->enum('kategori', [
                'nasional',
                'internasional',
                'lainnya',
            ]);

            $table->unsignedInteger('urutan')->nullable();

            $table->text('deskripsi')->nullable();

            $table->boolean('aktif')->default(true);

            $table->timestamps();

            $table->index('kategori');
            $table->index('urutan');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tingkat_pengindeksan');
    }
};

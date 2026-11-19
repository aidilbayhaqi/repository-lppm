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
        Schema::create('penulis_publikasi', function (Blueprint $table) {
            $table->id();

            $table->foreignId('publikasi_id')
            ->constrained('publikasi')
            ->cascadeOnUpdate()
            ->cascadeOnDelete();

            $table->foreignId('penulis_id')
                ->constrained('penulis')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->unsignedInteger('urutan_penulis');

            $table->boolean('penulis_pertama')->default(false);

            $table->boolean('penulis_korespondensi')->default(false);

            $table->boolean('peneliti_utama')->default(false);

            $table->enum('jenis_afiliasi', [
                'internal',
                'eksternal',
            ])->nullable();

            $table->timestamps();

            $table->unique([
                'publikasi_id',
                'penulis_id',
            ]);

            $table->index([
                'publikasi_id',
                'urutan_penulis',
            ]);

            $table->index('penulis_id');
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penulis_publikasi');
    }
};

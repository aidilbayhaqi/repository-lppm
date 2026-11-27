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
        Schema::create('pencipta_kekayaan_intelektual', function (Blueprint $table) {
            $table->id();

            $table->foreignId('kekayaan_intelektual_id')
                ->constrained('kekayaan_intelektual')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('penulis_id')
                ->constrained('penulis')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->unsignedInteger('urutan_pencipta');

            $table->timestamps();

            $table->unique(
                [
                    'kekayaan_intelektual_id',
                    'penulis_id',
                ],
                'uniq_hki_pencipta'
            );

            $table->index(
                [
                    'kekayaan_intelektual_id',
                    'urutan_pencipta',
                ],
                'idx_hki_urutan'
            );

            $table->index(
                'penulis_id',
                'idx_hki_penulis'
            );

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pencipta_kekayaan_intelektual');
    }
};

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
        Schema::create('penulis_buku', function (Blueprint $table) {
            $table->id();

               $table->foreignId('buku_id')
            ->constrained('buku')
            ->cascadeOnUpdate()
            ->cascadeOnDelete();

        $table->foreignId('penulis_id')
            ->constrained('penulis')
            ->cascadeOnUpdate()
            ->restrictOnDelete();

        $table->unsignedInteger('urutan_penulis');

        $table->boolean('penulis_korespondensi')
            ->default(false);

        $table->timestamps();

        $table->unique([
            'buku_id',
            'penulis_id',
        ]);

        $table->index([
            'buku_id',
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
        Schema::dropIfExists('penulis_buku');
    }
};

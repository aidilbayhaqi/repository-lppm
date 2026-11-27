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
        Schema::create('afiliasi_penulis', function (Blueprint $table) {
            $table->id();

            $table->foreignId('penulis_id')
                ->constrained('penulis')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('institusi', 150);
            $table->string('departemen', 150)->nullable();

            $table->boolean('afiliasi_utama')->default(false);

            $table->timestamps();

            $table->index('penulis_id');
            $table->index('institusi');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('afiliasi_penulis');
    }
};

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
        Schema::create('jurnal', function (Blueprint $table) {
            $table->id();

            $table->foreignId('penerbit_id')
                ->nullable()
                ->constrained('penerbit')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('nama_jurnal', 200);

            $table->string('issn_cetak', 20)->nullable();
            $table->string('issn_elektronik', 20)->nullable();

            $table->string('website', 255)->nullable();

            $table->boolean('aktif')->default(true);

            $table->index('nama_jurnal');
            $table->index('penerbit_id');
            $table->index('issn_cetak');
            $table->index('issn_elektronik');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jurnal');
    }
};

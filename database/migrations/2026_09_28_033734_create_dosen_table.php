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
        Schema::create('dosen', function (Blueprint $table) {
            $table->id();

            $table->foreignId('program_studi_id')
                ->constrained('program_studi')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('nidn', 20)->nullable()->unique();

            $table->string('nama', 150);
            $table->string('gelar', 100)->nullable();
            $table->string('email', 150)->nullable();

            $table->string('scopus_id', 50)->nullable();
            $table->string('orcid_id', 50)->nullable();
            $table->string('google_scholar_id', 100)->nullable();

            $table->boolean('aktif')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dosen');
    }
};

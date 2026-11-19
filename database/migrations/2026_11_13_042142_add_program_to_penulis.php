<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penulis', function (Blueprint $table) {
            $table->foreignId('program_studi_id')->nullable()->constrained('program_studi')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('penulis', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_studi_id');
        });
    }
};

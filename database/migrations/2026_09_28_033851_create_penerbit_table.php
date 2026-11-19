<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penerbit', function (Blueprint $table) {

            $table->id();


            /**
             * Informasi utama penerbit
             */
            $table->string('nama', 150);

            $table->string('kode', 50)
                  ->nullable()
                  ->unique();


            /**
             * Informasi kontak
             */
            $table->string('website', 255)
                  ->nullable();

            $table->string('email', 150)
                  ->nullable();

            $table->string('telepon', 50)
                  ->nullable();


            /**
             * Alamat penerbit
             */
            $table->text('alamat')
                  ->nullable();

            $table->string('kota',100)
                  ->nullable();

            $table->string('negara',100)
                  ->default('Indonesia');



            /**
             * Status data
             */
            $table->boolean('aktif')
                  ->default(true);



            /**
             * Audit
             */
            $table->timestamps();



            /**
             * Index
             */
            $table->index('nama');

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('penerbit');
    }
};
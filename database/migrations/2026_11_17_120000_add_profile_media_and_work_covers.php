<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('jabatan', 120)->nullable();
            $table->text('bio')->nullable();
            $table->string('profile_photo_path', 500)->nullable();
            $table->string('profile_photo_disk', 50)->nullable();
        });

        Schema::create('work_covers', function (Blueprint $table): void {
            $table->id();
            $table->morphs('subject');
            $table->unique(['subject_type', 'subject_id'], 'work_cover_subject_unique');
            $table->string('path', 500);
            $table->string('disk', 50);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        foreach (['publikasi', 'buku', 'kekayaan_intelektual'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->index(
                    [
                        $tableName === 'publikasi' ? 'repository_status' : 'status',
                        'public_visibility',
                        'archived_at',
                        ...($tableName === 'publikasi' ? ['repository_published_at'] : ['updated_at']),
                        'id',
                    ],
                    match ($tableName) {
                        'publikasi' => 'pub_public_recent',
                        'buku' => 'book_public_recent',
                        default => 'hki_public_recent',
                    }
                );
            });
        }
    }

    public function down(): void
    {
        foreach (['publikasi' => 'pub_public_recent', 'buku' => 'book_public_recent', 'kekayaan_intelektual' => 'hki_public_recent'] as $tableName => $name) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropIndex($name));
        }
        Schema::dropIfExists('work_covers');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['jabatan', 'bio', 'profile_photo_path', 'profile_photo_disk']));
    }
};

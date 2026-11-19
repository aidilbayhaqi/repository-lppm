<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 40)->default('mahasiswa')->change();
        });
        foreach (['publikasi', 'buku', 'kekayaan_intelektual'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('public_visibility', 20)->default('private');
                $table->unsignedInteger('lock_version')->default(0);
                $table->timestamp('archived_at')->nullable();
            });
        }
        DB::table('publikasi')->where('repository_status', 'published')->update(['public_visibility' => 'public']);
        foreach (['buku', 'kekayaan_intelektual'] as $name) {
            DB::table($name)->where('status', 'dipublikasikan')->update(['public_visibility' => 'public']);
        }
        Schema::table('publikasi', function (Blueprint $table) {
            $table->index(['repository_status', 'public_visibility', 'tahun_publikasi'], 'publication_discovery');
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('created_at');
            $table->index(['subject_type', 'subject_id', 'created_at'], 'audit_subject_time');
        });
        Schema::create('verification_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publikasi_id')->constrained('publikasi')->restrictOnDelete();
            $table->foreignId('verifier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30);
            $table->text('notes')->nullable();
            $table->timestamp('created_at');
        });
        Schema::create('publish_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publikasi_id')->constrained('publikasi')->restrictOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('queued')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->unique(['publikasi_id', 'attempt_number']);
        });
        Schema::table('dokumen', function (Blueprint $table) {
            $table->string('storage_disk', 30)->default('local');
            $table->string('checksum', 64)->nullable();
            $table->unsignedInteger('current_version')->default(1);
        });
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dokumen_id')->constrained('dokumen')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('storage_disk', 30);
            $table->string('path_file', 500);
            $table->string('nama_file_asli', 255);
            $table->unsignedBigInteger('ukuran_file');
            $table->string('mime_type', 100);
            $table->string('checksum', 64)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->unique(['dokumen_id', 'version_number']);
        });
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 20);
            $table->string('status', 20)->default('preview');
            $table->string('filename');
            $table->json('rows');
            $table->json('errors');
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('document_versions');
        Schema::table('dokumen', fn (Blueprint $table) => $table->dropColumn(['storage_disk', 'checksum', 'current_version']));
        Schema::dropIfExists('publish_attempts');
        Schema::dropIfExists('verification_histories');
        Schema::dropIfExists('audit_logs');
        Schema::table('publikasi', fn (Blueprint $table) => $table->dropIndex('publication_discovery'));
        foreach (['publikasi', 'buku', 'kekayaan_intelektual'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('created_by');
                $table->dropColumn(['public_visibility', 'lock_version', 'archived_at']);
            });
        }
    }
};

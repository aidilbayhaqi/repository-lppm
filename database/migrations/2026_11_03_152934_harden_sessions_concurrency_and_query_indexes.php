<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $references = ['fakultas', 'program_studi', 'dosen', 'penulis', 'afiliasi_penulis', 'penerbit', 'jurnal', 'pengindeks', 'tingkat_pengindeksan'];

    private array $indexes = [
        'publikasi' => [
            'pub_public_year' => ['repository_status', 'public_visibility', 'archived_at', 'tahun_publikasi'],
            'pub_owner_recent' => ['created_by', 'created_at', 'id'],
        ],
        'buku' => [
            'book_public_year' => ['status', 'public_visibility', 'archived_at', 'tahun_publikasi'],
            'book_owner_recent' => ['created_by', 'created_at', 'id'],
        ],
        'kekayaan_intelektual' => [
            'hki_public_year' => ['status', 'public_visibility', 'archived_at', 'tahun'],
            'hki_owner_recent' => ['created_by', 'created_at', 'id'],
        ],
        'penulis' => ['author_active_name' => ['aktif', 'nama', 'id']],
        'dosen' => ['lecturer_active_name' => ['aktif', 'nama', 'id']],
        'audit_logs' => ['audit_subject_id' => ['subject_type', 'subject_id', 'id']],
        'verification_histories' => ['verification_work_id' => ['publikasi_id', 'id']],
        'publish_attempts' => ['attempt_work_id' => ['publikasi_id', 'id']],
        'import_batches' => ['import_owner_id' => ['user_id', 'id']],
        'users' => ['user_role_active' => ['role', 'is_active', 'id']],
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('auth_version')->default(0);
            $table->unsignedInteger('lock_version')->default(0);
        });
        foreach ($this->references as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->unsignedInteger('lock_version')->default(0));
        }
        foreach (['dokumen', 'document_versions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('scan_status', 20)->default('unscanned');
                $table->timestamp('scanned_at')->nullable();
            });
        }
        foreach ($this->indexes as $name => $indexes) {
            Schema::table($name, function (Blueprint $table) use ($indexes): void {
                foreach ($indexes as $index => $columns) {
                    $table->index($columns, $index);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $name => $indexes) {
            Schema::table($name, function (Blueprint $table) use ($indexes): void {
                foreach (array_keys($indexes) as $index) {
                    $table->dropIndex($index);
                }
            });
        }
        foreach (['dokumen', 'document_versions'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['scan_status', 'scanned_at']));
        }
        foreach ($this->references as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('lock_version'));
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['auth_version', 'lock_version']));
    }
};

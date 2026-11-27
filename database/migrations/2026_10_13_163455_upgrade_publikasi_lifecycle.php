<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Upgrade publication lifecycle.
     */
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Add new lifecycle columns
        |--------------------------------------------------------------------------
        */

        Schema::table('publikasi', function (Blueprint $table) {
            $table->string('academic_status', 50)
                ->nullable()
                ->after('klasifikasi');

            $table->string('repository_status', 30)
                ->default('draft')
                ->after('academic_status');

            $table->timestamp('repository_published_at')
                ->nullable()
                ->after('repository_status');

            $table->index(
                'academic_status',
                'publikasi_academic_status_index'
            );

            $table->index(
                'repository_status',
                'publikasi_repository_status_index'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Migrate old status values
        |--------------------------------------------------------------------------
        |
        | Old:
        |   draft
        |   dipublikasikan
        |
        | New:
        |   draft
        |   published
        |
        */

        DB::table('publikasi')
            ->where('status', 'dipublikasikan')
            ->update([
                'repository_status' => 'published',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Remove legacy status column
        |--------------------------------------------------------------------------
        */

        Schema::table('publikasi', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }

    /**
     * Rollback publication lifecycle.
     */
    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Restore legacy status column
        |--------------------------------------------------------------------------
        */

        Schema::table('publikasi', function (Blueprint $table) {
            $table->enum('status', [
                'draft',
                'dipublikasikan',
            ])
                ->default('draft')
                ->after('repository_published_at');

            $table->index('status');
        });

        /*
        |--------------------------------------------------------------------------
        | Restore old published status
        |--------------------------------------------------------------------------
        */

        DB::table('publikasi')
            ->where(
                'repository_status',
                'published'
            )
            ->update([
                'status' => 'dipublikasikan',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Remove new lifecycle columns
        |--------------------------------------------------------------------------
        */

        Schema::table('publikasi', function (Blueprint $table) {
            $table->dropIndex([
                'academic_status',
            ]);

            $table->dropIndex([
                'repository_status',
            ]);

            $table->dropColumn([
                'academic_status',
                'repository_status',
                'repository_published_at',
            ]);
        });
    }
};

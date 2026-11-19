<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publikasi', function (Blueprint $table) {
            $table->string('publication_type', 30)->nullable()->index();
            $table->string('journal_name_manual', 200)->nullable();
            $table->string('indexing_mode', 20)->default('reference');
            $table->unsignedTinyInteger('sinta_rank')->nullable()->index();
            $table->boolean('scopus_indexed')->default(false)->index();
            $table->string('scopus_quartile', 2)->nullable()->index();
            $table->unsignedSmallInteger('indexing_year')->nullable();
            $table->string('indexing_source', 500)->nullable();
        });
        Schema::table('users', fn (Blueprint $table) => $table->timestamp('remember_expires_at')->nullable());
        foreach (['SINTA' => 'nasional', 'Scopus' => 'internasional'] as $name => $category) {
            if (! DB::table('pengindeks')->where('nama', $name)->exists()) {
                DB::table('pengindeks')->insert(['nama' => $name, 'kategori' => $category, 'aktif' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        foreach (['S' => 6, 'Q' => 4] as $prefix => $maximum) {
            foreach (range(1, $maximum) as $rank) {
                $code = $prefix.$rank;
                if (! DB::table('tingkat_pengindeksan')->where('kode', $code)->exists()) {
                    DB::table('tingkat_pengindeksan')->insert(['kode' => $code, 'nama' => $prefix === 'S' ? 'SINTA '.$rank : 'Scopus Q'.$rank, 'kategori' => $prefix === 'S' ? 'nasional' : 'internasional', 'urutan' => $rank, 'aktif' => true, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }

        // Only recognize explicit legacy labels; unknown rankings remain unset.
        DB::table('pengindeksan_publikasi as pivot')
            ->join('pengindeks as idx', 'idx.id', '=', 'pivot.pengindeks_id')
            ->leftJoin('tingkat_pengindeksan as level', 'level.id', '=', 'pivot.tingkat_pengindeksan_id')
            ->select(['pivot.id', 'pivot.publikasi_id', 'idx.nama', 'level.kode', 'level.nama as level_name'])
            ->orderBy('pivot.id')->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $name = strtolower(trim($row->nama));
                    $code = preg_replace('/[^a-z0-9]/', '', strtolower($row->kode ?? ''));
                    $label = preg_replace('/[^a-z0-9]/', '', strtolower($row->level_name ?? ''));
                    if ($name === 'sinta' && (preg_match('/^(?:s|sinta)([1-6])$/', $code, $m) || preg_match('/^sinta([1-6])$/', $label, $m))) {
                        DB::table('publikasi')->where('id', $row->publikasi_id)->update(['sinta_rank' => (int) $m[1]]);
                    }
                    if ($name === 'scopus') {
                        $matched = preg_match('/^(?:scopus)?q([1-4])$/', $code, $m) || preg_match('/^(?:scopus)?q([1-4])$/', $label, $m);
                        DB::table('publikasi')->where('id', $row->publikasi_id)->update(['scopus_indexed' => true, 'scopus_quartile' => $matched ? 'Q'.$m[1] : null]);
                    }
                }
            }, 'pivot.id', 'id');
    }

    public function down(): void
    {
        Schema::table('publikasi', function (Blueprint $table) {
            foreach (['publication_type', 'sinta_rank', 'scopus_indexed', 'scopus_quartile'] as $column) {
                $table->dropIndex([$column]);
            }
            $table->dropColumn(['publication_type', 'journal_name_manual', 'indexing_mode', 'sinta_rank', 'scopus_indexed', 'scopus_quartile', 'indexing_year', 'indexing_source']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('remember_expires_at'));
    }
};

#!/usr/bin/env php
<?php

/**
 * Tambah blok PHPDoc @property ke model Eloquent, tipe diambil dari
 * SKEMA MIGRASI (bukan tebakan). Jalankan sekali, hasil di-commit.
 *
 * Sumber tipe, berurutan prioritas:
 *   1. casts()          -> tipe cast
 *   2. definisi kolom   -> dari Blueprint di database/migrations
 *   3. fallback         -> string|null (kolom tak ditemukan)
 */
$models = [
    'User', 'Publikasi', 'Buku', 'KekayaanIntelektual', 'Dokumen',
    'Penulis', 'Jurnal', 'Penerbit', 'Fakultas', 'ProgramStudi',
    'AfiliasiPenulis', 'AuditLog', 'ImportBatch', 'RegistrationRequest',
    'Sitasi', 'VerificationHistory', 'WorkCover', 'DocumentVersion',
    'PublishAttempt', 'Dosen', 'Pengindeks', 'TingkatPengindeksan',
];

$castMap = [
    'int' => 'int', 'integer' => 'int',
    'real' => 'float', 'float' => 'float', 'double' => 'float',
    'decimal' => 'string',
    'string' => 'string',
    'bool' => 'bool', 'boolean' => 'bool',
    'array' => 'array<int, mixed>', 'json' => 'array<string, mixed>',
    'object' => 'object',
    'date' => '\Illuminate\Support\Carbon', 'datetime' => '\Illuminate\Support\Carbon',
    'immutable_date' => '\Carbon\Immutable', 'immutable_datetime' => '\Carbon\Immutable',
    'timestamp' => 'int',
    'hashed' => 'string',
    'encrypted' => 'string',
];

// Tipe kolom dari Blueprint, plus apakah kolom itu nullable secara default.
// $table->string('x')     -> string|null  (modifier nullable() menentukan)
// $table->string('x')->nullable() -> string|null
// $table->string('x')->default('y') -> string
$colMap = [
    'id' => ['int', false],
    'foreignId' => ['int', true],
    'unsignedBigInteger' => ['int', false],
    'unsignedInteger' => ['int', false],
    'integer' => ['int', false],
    'boolean' => ['bool', false],
    'string' => ['string', false],
    'char' => ['string', false],
    'text' => ['string', false],
    'longText' => ['string', false],
    'json' => ['array<string, mixed>', false],
    'uuid' => ['string', false],
    'timestamp' => ['\Illuminate\Support\Carbon', false],
    'timestamps' => ['\Illuminate\Support\Carbon', true],
    'date' => ['\Illuminate\Support\Carbon', false],
    'decimal' => ['string', false],
];

$root = __DIR__.'/..';
$migrationDir = $root.'/database/migrations';

/** Kumpulkan definisi kolom untuk semua tabel dari migrasi. */
$schema = [];
foreach (glob($migrationDir.'/*.php') as $file) {
    $src = file_get_contents($file);
    // Schema::create('nama', function (Blueprint $table) { ... });
    if (preg_match_all("/Schema::create\('([^']+)',\s*function\s*\(\s*Blueprint\s*\\\$table\s*\)\s*\{(.*?)\n\s*\}\);/s", $src, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $m) {
            $table = $m[1];
            $body = $m[2];
            $schema[$table] = $schema[$table] ?? [];
            // $table->tipe('kolom')...->nullable()   (juga: ->string('x', 20))
            preg_match_all("/\\\$table->(\w+)\(\s*'([^']+)'(?:\s*,\s*[^)]*)?\)([^;]*);/", $body, $cm, PREG_SET_ORDER);
            foreach ($cm as $c) {
                $call = $c[1];
                $col = $c[2];
                $chain = $c[3];
                if (isset($colMap[$call])) {
                    [$type, $defaultNullable] = $colMap[$call];
                } else {
                    [$type, $defaultNullable] = ['string', true];
                }
                $nullable = str_contains($chain, '->nullable()') || $defaultNullable;
                if (str_contains($chain, '->default(') && ! str_contains($chain, '->default(null)')) {
                    $nullable = false;
                }
                if (str_contains($chain, '->primary()')) {
                    $nullable = false;
                }
                $schema[$table][$col] = ['type' => $type, 'nullable' => $nullable];
            }
            // $table->id();  -> kolom id
            if (preg_match('/\\$table->id\(\)/', $body)) {
                $schema[$table]['id'] = ['type' => 'int', 'nullable' => false];
            }
            // $table->timestamps();  -> created_at, updated_at
            if (str_contains($body, 'timestamps()')) {
                $schema[$table]['created_at'] = ['type' => '\Illuminate\Support\Carbon', 'nullable' => false];
                $schema[$table]['updated_at'] = ['type' => '\Illuminate\Support\Carbon', 'nullable' => false];
            }
            if (str_contains($body, 'softDeletes()')) {
                $schema[$table]['deleted_at'] = ['type' => '\Illuminate\Support\Carbon', 'nullable' => true];
            }
        }
    }
    // Schema::table('nama', function (Blueprint $table) { $table->tipe('kolom'); });
    if (preg_match_all("/Schema::table\('([^']+)',\s*function\s*\(\s*Blueprint\s*\\\$table\s*\)\s*\{(.*?)\n\s*\}\);/s", $src, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $m) {
            $table = $m[1];
            $schema[$table] = $schema[$table] ?? [];
            preg_match_all("/\\\$table->(\w+)\('([^']+)'\)([^;]*);/", $m[2], $cm, PREG_SET_ORDER);
            foreach ($cm as $c) {
                if (in_array($c[1], ['dropColumn', 'dropIndex', 'index', 'unique', 'foreign'], true)) {
                    continue;
                }
                if (isset($colMap[$c[1]])) {
                    [$type, $defNullable] = $colMap[$c[1]];
                } else {
                    continue;
                }
                $nullable = str_contains($c[3], '->nullable()') || $defNullable;
                if (str_contains($c[3], '->default(') && ! str_contains($c[3], '->default(null)')) {
                    $nullable = false;
                }
                $schema[$table][$c[2]] = ['type' => $type, 'nullable' => $nullable];
            }
        }
    }
}

$report = [];
$modelsDir = $root.'/app/Models';

foreach ($models as $name) {
    $path = "$modelsDir/$name.php";
    if (! file_exists($path)) {
        $report[] = "SKIP $name (tidak ada)";

        continue;
    }
    $src = file_get_contents($path);
    if (str_contains($src, '@property ')) {
        $report[] = "SKIP $name (sudah punya @property)";

        continue;
    }

    $tableName = null;
    if (preg_match("/protected \\\$table\s*=\s*'([^']+)'/", $src, $tm)) {
        $tableName = $tm[1];
    } else {
        // konvensi Laravel: snake_case + plural (batch -> batches)
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
        $candidates = [
            $snake,
            $snake.'s',
            str_ends_with($snake, 'h') ? $snake.'es' : $snake.'s',
            str_ends_with($snake, 'y') ? substr($snake, 0, -1).'ies' : $snake.'s',
        ];
        foreach ($candidates as $cand) {
            if (isset($schema[$cand])) {
                $tableName = $cand;
                break;
            }
        }
        $tableName ??= $snake;
    }

    $columns = $schema[$tableName] ?? [];

    $fillable = [];
    if (preg_match('/protected \$fillable = \[(.*?)\];/s', $src, $m)) {
        preg_match_all("/'([^']+)'/", $m[1], $fm);
        $fillable = $fm[1];
    } else {
        $fillable = array_keys($columns);
    }

    $casts = [];
    if (preg_match('/protected function casts\(\): array\s*\{(.*?)\n    \}/s', $src, $m)) {
        preg_match_all("/'([^']+)'\s*=>\s*'([^']+)'/", $m[1], $cm, PREG_SET_ORDER);
        foreach ($cm as $c) {
            $casts[$c[1]] = $c[2];
        }
    }

    $relations = [];
    preg_match_all('/public function (\w+)\(\)[^{]*\{\s*return \$this->(hasMany|hasOne|belongsTo|belongsToMany|morphMany|morphOne|morphTo)\(([^)]*)/s', $src, $rm, PREG_SET_ORDER);
    foreach ($rm as $r) {
        $fn = $r[1];
        $kind = $r[2];
        $target = null;
        if (preg_match('/(\w+)::class/', $r[3], $tgt)) {
            $target = $tgt[1];
        }
        if ($kind === 'morphTo') {
            $relations[] = " * @property-read \\Illuminate\\Database\\Eloquent\\Model|null \$$fn";
        } elseif ($target) {
            $type = in_array($kind, ['hasMany', 'belongsToMany', 'morphMany'], true)
                ? '\\Illuminate\\Database\\Eloquent\\Collection<int, \\App\\Models\\'.$target.'>'
                : '\\App\\Models\\'.$target;
            $relations[] = " * @property-read $type|null \$$fn";
        }
    }

    $lines = ['/**'];
    $seen = [];
    // casts() selalu menunjuk kolom nyata; gabungkan agar kolom yang ditambah
    // lewat migrasi Schema::table (format berbeda) tetap terdokumentasi.
    $cols = array_unique(array_merge($fillable, array_keys($columns), array_keys($casts)));
    sort($cols);
    foreach ($cols as $col) {
        if (isset($seen[$col])) {
            continue;
        }
        $seen[$col] = true;
        if (isset($casts[$col])) {
            $type = $castMap[$casts[$col]] ?? 'string';
            $nullable = false;
        } elseif (isset($columns[$col])) {
            $type = $columns[$col]['type'];
            $nullable = $columns[$col]['nullable'];
        } else {
            // Kolom di $fillable tapi tak ada di skema: jangan mengarang.
            continue;
        }
        if ($nullable) {
            $type .= '|null';
        }
        $lines[] = " * @property $type \$$col";
    }
    foreach ($relations as $rel) {
        $lines[] = $rel;
    }
    $lines[] = ' */';

    if (count($lines) <= 2) {
        $report[] = "SKIP $name (nol property terdeteksi)";

        continue;
    }

    $doc = implode("\n", $lines)."\n";
    $src = preg_replace('/(\nclass '.$name.'\b)/', "\n".$doc.'$1', $src, 1);
    file_put_contents($path, $src);
    $report[] = "OK $name -> $tableName (".(count($lines) - 2).' baris doc)';
}

echo implode("\n", $report)."\n";

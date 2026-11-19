<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Dosen;
use App\Models\Jurnal;
use App\Models\Penerbit;
use App\Models\Penulis;
use App\Models\ProgramStudi;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class InlineReferenceService
{
    public static function normalized(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)));
    }

    /** Called only inside the authorized work transaction, never during import preview. */
    public function resolve(string $type, array $data, User $actor): array
    {
        $this->preflight($type, $data);
        $ids = [];
        foreach ($data['authors'] as $i => &$row) {
            if (empty($row['id']) || ($row['mode'] ?? '') === 'manual') {
                $row['id'] = $this->author($row, $actor, "authors.$i")->id;
            }
            if (isset($ids[$row['id']])) {
                $this->invalid("authors.$i.id", 'Penulis yang sama tercatat dua kali. Hapus salah satu baris.');
            }
            $ids[$row['id']] = true;
        }
        unset($row);

        if ($type === 'publikasi' && ($data['journal_source'] ?? '') === 'manual') {
            $name = trim($data['journal_name_manual']);
            $journal = $this->named(Jurnal::class, 'nama_jurnal', $name, [
                'issn_cetak' => $data['issn_cetak'] ?? null,
                'issn_elektronik' => $data['issn_elektronik'] ?? null,
            ], $actor, 'journal_name_manual');
            foreach (['issn_cetak', 'issn_elektronik'] as $field) {
                if (filled($data[$field] ?? null) && filled($journal->$field) && str_replace('-', '', $journal->$field) !== str_replace('-', '', $data[$field])) {
                    $this->invalid($field, 'Nama jurnal sudah ada dengan ISSN berbeda. Periksa nama dan ISSN atau pilih referensi yang sesuai.');
                }
            }
            $data['jurnal_id'] = $journal->id;
            $data['journal_source'] = 'reference';
            $data['journal_name_manual'] = null;
        }
        if ($type === 'buku' && ($data['publisher_source'] ?? '') === 'manual') {
            $data['penerbit_id'] = $this->named(Penerbit::class, 'nama', trim($data['publisher_name']), [], $actor, 'publisher_name')->id;
        }

        return $data;
    }

    public function preflight(string $type, array $data): void
    {
        foreach ($data['authors'] as $i => $row) {
            // Never create faculties or study programs through work submissions.
            foreach (['program_name', 'faculty_name'] as $field) {
                if (filled($row[$field] ?? null)) {
                    $this->invalid("authors.$i.$field", 'Fakultas dan Prodi hanya dapat dipilih dari Data Referensi.');
                }
            }
            if (! empty($row['id']) && ($row['mode'] ?? '') !== 'manual') {
                continue;
            }
            if (($row['jenis_penulis'] ?? '') !== 'dosen') {
                if (filled($row['program_studi_id'] ?? null)) {
                    $this->invalid("authors.$i.program_studi_id", 'Program studi hanya boleh dipilih untuk penulis berjenis dosen.');
                }
                continue;
            }

            $lecturer = Dosen::where('nidn', $row['nidn'])->first();
            if ($lecturer) {
                $this->assertActive($lecturer, "authors.$i.nidn");
                if (self::normalized($lecturer->nama) !== self::normalized($row['nama'])) {
                    $this->invalid("authors.$i.nidn", 'NIDN tersebut sudah terhubung dengan nama lain. Periksa nama dan NIDN.');
                }
            }

            $program = $this->program($row, "authors.$i");
            if ($lecturer && (int) $lecturer->program_studi_id !== (int) $program->id) {
                $this->invalid("authors.$i.program_studi_id", 'Program studi dosen tidak cocok dengan Data Referensi. Hubungi Admin untuk memperbarui data dosen.');
            }
        }
        if ($type === 'publikasi' && ($data['journal_source'] ?? '') === 'manual') {
            $journal = Jurnal::whereRaw('LOWER(TRIM(nama_jurnal)) = ?', [self::normalized($data['journal_name_manual'])])->first();
            if ($journal) {
                $this->assertActive($journal, 'journal_name_manual');
                foreach (['issn_cetak', 'issn_elektronik'] as $field) {
                    if (filled($data[$field] ?? null) && filled($journal->$field) && strtoupper(str_replace('-', '', $journal->$field)) !== strtoupper(str_replace('-', '', $data[$field]))) {
                        $this->invalid($field, 'Nama jurnal sudah ada dengan ISSN berbeda. Periksa nama dan ISSN.');
                    }
                }
            }
        }
    }

    private function author(array $row, User $actor, string $field): Penulis
    {
        $name = trim($row['nama']);
        $kind = $row['jenis_penulis'];
        $normal = self::normalized($name);
        $lecturer = null;
        if ($kind === 'dosen') {
            $nidn = $row['nidn'];
            $lecturer = Dosen::where('nidn', $nidn)->first();
            if (! $lecturer) {
                $program = $this->program($row, $field);
                $lecturer = Dosen::firstOrCreate(['nidn' => $nidn], ['nama' => $name, 'program_studi_id' => $program?->id, 'aktif' => true]);
                $this->recordNew($lecturer, $actor, $name);
            }
            $this->assertActive($lecturer, "$field.nidn");
            if (self::normalized($lecturer->nama) !== $normal) {
                $this->invalid("$field.nidn", 'NIDN tersebut sudah terhubung dengan nama lain. Periksa NIDN atau gunakan pilihan penulis yang tersedia.');
            }
            $existing = Penulis::where('dosen_id', $lecturer->id)->first();
            if ($existing) {
                $this->assertActive($existing, "$field.nama");

                return $existing;
            }
            $identity = 'dosen:'.$nidn;
        } else {
            $identity = filled($row['identitas'] ?? null)
                ? $kind.':'.$normal.':'.self::normalized($row['identitas'])
                : $kind.':'.$normal.':contributor:'.$actor->id;
        }
        $key = hash('sha256', $identity);
        $author = Penulis::where('entry_key', $key)->first();
        if (! $author && $kind !== 'dosen' && filled($row['identitas'] ?? null)) {
            $author = Penulis::where('jenis_penulis', $kind)->where('nama_normalisasi', $normal)->where('identitas', $row['identitas'])->first();
        }
        if (! $author) {
            // No submitted field can link a login account, assign a role, or overwrite a master record.
            $author = Penulis::firstOrCreate(['entry_key' => $key], [
                'nama' => $name, 'nama_normalisasi' => $normal, 'jenis_penulis' => $kind,
                'identitas' => $kind === 'dosen' ? $row['nidn'] : ($row['identitas'] ?? null),
                'dosen_id' => $lecturer?->id, 'aktif' => true,
            ]);
            $this->recordNew($author, $actor, $name);
        }
        $this->assertActive($author, "$field.nama");
        if ($author->jenis_penulis !== $kind || self::normalized($author->nama) !== $normal
            || ($kind !== 'dosen' && self::normalized($author->identitas ?? '') !== self::normalized($row['identitas'] ?? ''))) {
            $this->invalid("$field.nama", 'Referensi penulis ini telah diperbarui. Pilih data terbaru dari rekomendasi atau periksa identitasnya.');
        }

        return $author;
    }

    private function program(array $row, string $field): ProgramStudi
    {
        if (! filled($row['program_studi_id'] ?? null) || filled($row['program_name'] ?? null) || filled($row['faculty_name'] ?? null)) {
            $this->invalid("$field.program_studi_id", 'Dosen baru wajib memilih program studi dari Data Referensi.');
        }

        $program = ProgramStudi::query()
            ->whereKey($row['program_studi_id'])
            ->where('aktif', true)
            ->whereHas('fakultas', fn ($query) => $query->where('aktif', true))
            ->first();

        if (! $program) {
            $this->invalid("$field.program_studi_id", 'Program studi tidak tersedia atau fakultasnya nonaktif. Hubungi pengelola referensi.');
        }

        return $program;
    }

    private function named(string $class, string $column, string $name, array $extra, User $actor, string $field): Model
    {
        $normal = self::normalized($name);
        $key = hash('sha256', $class.':'.$normal);
        $model = $class::where('entry_key', $key)->first()
            ?? $class::whereRaw("LOWER(TRIM($column)) = ?", [$normal])->first()
            ?? $class::firstOrCreate(['entry_key' => $key], [$column => $name, 'aktif' => true] + $extra);
        $this->assertActive($model, $field);
        if (self::normalized($model->$column) !== $normal) {
            $this->invalid($field, 'Referensi ini telah berganti nama. Pilih nama terbaru dari rekomendasi.');
        }
        $this->recordNew($model, $actor, $name);

        return $model;
    }

    private function assertActive(Model $model, string $field): void
    {
        if (! $model->aktif) {
            $this->invalid($field, 'Referensi ini dinonaktifkan. Hubungi pengelola untuk memeriksanya.');
        }
    }

    private function recordNew(Model $model, User $actor, string $name): void
    {
        if ($model->wasRecentlyCreated) {
            AuditLog::record($model, $actor, 'reference_created', [], ['nama' => $name], 'Ditambahkan melalui form karya atau import.');
        }
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}

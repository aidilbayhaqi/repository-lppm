<?php

namespace App\Services;

use App\Http\Requests\WorkRequest;
use App\Models\AuditLog;
use App\Models\ImportBatch;
use App\Models\ProgramStudi;
use App\Models\User;
use App\Support\JournalMetadata;
use App\Support\WorkType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportService
{
    public function headers(string $type): array
    {
        WorkType::model($type);
        $fields = array_keys(WorkRequest::rulesFor($type));
        $fields = array_values(array_filter($fields, fn ($f) => ! str_contains($f, '.') && ! in_array($f, ['authors', 'citations', 'indexers', 'lock_version', 'attachments', 'faculty_name', 'program_name'])));

        return [...$fields, 'penulis_ids', 'penulis_nama', 'penulis_jenis', 'penulis_nidn', 'penulis_identitas', 'penulis_prodi_id', 'penulis_prodi', 'penulis_fakultas', ...($type === 'publikasi' ? ['pengindeks_ids', 'sitasi_'.date('Y')] : [])];
    }

    public function preview(string $type, array $sheet, User $actor, string $filename): ImportBatch
    {
        Gate::forUser($actor)->authorize('manage-reference');
        $headers = array_map(fn ($s) => strtolower(trim($s)), array_shift($sheet));
        if (count($headers) !== count(array_unique($headers))) {
            throw ValidationException::withMessages(['file' => 'Header kolom tidak boleh berulang.']);
        }
        $allowed = $this->headers($type);
        foreach ($headers as $header) {
            if (! in_array($header, $allowed) && ! ($type === 'publikasi' && preg_match('/^sitasi_(19|20)\d{2}$/', $header))) {
                throw ValidationException::withMessages(['file' => 'Kolom tidak dikenali: '.$header.'. Gunakan template import.']);
            }
        }
        if (! in_array('judul', $headers) || (! in_array('penulis_ids', $headers) && ! in_array('penulis_nama', $headers))) {
            throw ValidationException::withMessages(['file' => 'Kolom judul serta penulis_ids atau penulis_nama wajib tersedia.']);
        }
        $rows = [];
        $errors = [];
        $seen = [];
        foreach ($sheet as $index => $values) {
            $line = $index + 2;
            if (! array_filter($values, fn ($value) => $value !== null && trim((string) $value) !== '')) {
                continue;
            }
            if (count($values) > count($headers)) {
                $errors[$line] = ['Jumlah kolom melebihi header.'];

                continue;
            }
            $data = array_combine($headers, array_pad($values, count($headers), null));
            $data = array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $data);
            if (filled($data['penulis_ids'] ?? null) && filled($data['penulis_nama'] ?? null)) {
                $errors[$line] = ['Pilih salah satu: penulis_ids atau penulis_nama pada setiap baris.'];

                continue;
            }
            $authorCount = count(explode('|', $data['penulis_nama'] ?? '', 51));
            foreach (['jenis', 'nidn', 'identitas', 'prodi_id', 'prodi', 'fakultas'] as $column) {
                if (count(explode('|', $data['penulis_'.$column] ?? '', 51)) > $authorCount) {
                    $errors[$line] = ['Jumlah isian penulis_'.$column.' melebihi jumlah nama. Samakan urutan kolom penulis.'];

                    continue 2;
                }
            }
            try {
                $data['authors'] = $this->authors($data);
            } catch (ValidationException $exception) {
                $errors[$line] = $exception->validator->errors()->all();

                continue;
            }
            $data['public_visibility'] = $data['public_visibility'] ?? 'private';
            $data['doi'] = WorkRequest::normalizeDoi($data['doi'] ?? null);
            $data['isbn'] = preg_replace('/[\s-]+/', '', strtoupper($data['isbn'] ?? '')) ?: null;
            if ($type === 'publikasi') {
                $data['journal_source'] ??= filled($data['journal_name_manual'] ?? null) ? 'manual' : 'reference';
                $data['indexing_mode'] ??= filled($data['sinta_rank'] ?? null) || filled($data['scopus_quartile'] ?? null) || filled($data['scopus_indexed'] ?? null) ? 'manual' : 'reference';
                if (filled($data['scopus_quartile'] ?? null) && ! filled($data['scopus_indexed'] ?? null)) {
                    $data['scopus_indexed'] = '1';
                }
            }
            if ($type === 'buku') {
                $data['publisher_source'] ??= filled($data['publisher_name'] ?? null) ? 'manual' : 'reference';
            }
            $data['citations'] = [];
            foreach ($data as $key => $value) {
                if (preg_match('/^sitasi_(\d{4})$/', $key, $match) && $value !== null) {
                    $data['citations'][] = ['tahun' => (int) $match[1], 'jumlah_sitasi' => $value, 'sumber' => 'google_scholar', 'cakupan' => 'umum'];
                }
            }
            $data['indexers'] = array_map(fn ($id) => ['id' => trim($id)], array_values(array_filter(explode('|', $data['pengindeks_ids'] ?? ''))));
            $validator = Validator::make($data, WorkRequest::rulesFor($type));
            if ($validator->fails()) {
                $errors[$line] = $validator->errors()->all();

                continue;
            }
            $validated = $validator->validated();
            try {
                app(InlineReferenceService::class)->preflight($type, $validated);
                if ($type === 'publikasi') {
                    $validated = JournalMetadata::normalize($validated);
                }
            } catch (ValidationException $error) {
                $errors[$line] = $error->validator->errors()->all();

                continue;
            }
            $key = ($type === 'publikasi' ? ($validated['doi'] ?? null) : ($type === 'buku' ? ($validated['isbn'] ?? null) : ($validated['nomor_pencatatan'] ?? null)));
            $titleKey = mb_strtolower($validated['judul']).'|'.($validated[WorkType::year($type)] ?? '').'|'.($validated['jurnal_id'] ?? $validated['penerbit_id'] ?? InlineReferenceService::normalized($validated['journal_name_manual'] ?? $validated['publisher_name'] ?? ''));
            $duplicateKey = $key ? 'identifier:'.$key : 'candidate:'.$titleKey;
            $existing = $this->candidate($type, $validated);
            if (isset($seen[$duplicateKey]) || (! $key && $existing->exists())) {
                $errors[$line] = ['Kandidat duplikat ditemukan. Periksa identifier dan metadata secara manual.'];

                continue;
            }
            if ($type === 'hki' && $key && WorkType::model($type)::where('nomor_pencatatan', $key)->exists()) {
                $errors[$line] = ['Nomor pencatatan HKI sudah digunakan.'];

                continue;
            }
            $seen[$duplicateKey] = true;
            $rows[] = ['line' => $line, 'data' => $validated];
        }

        return ImportBatch::create(['token' => (string) Str::uuid(), 'user_id' => $actor->id, 'type' => $type, 'filename' => mb_substr($filename, 0, 250), 'rows' => $rows, 'errors' => $errors, 'status' => 'preview']);
    }

    private function authors(array $data): array
    {
        if (filled($data['penulis_ids'] ?? null)) {
            return array_map(fn ($id) => ['id' => trim($id)], explode('|', $data['penulis_ids'], 51));
        }
        $columns = [];
        foreach (['nama', 'jenis', 'nidn', 'identitas', 'prodi_id', 'prodi', 'fakultas'] as $column) {
            $columns[$column] = array_map('trim', explode('|', $data['penulis_'.$column] ?? '', 51));
        }
        if (! filled($data['penulis_nama'] ?? null)) {
            return [];
        }

        return array_map(function ($name, $i) use ($columns) {
            $kind = ($columns['jenis'][$i] ?? '') ?: 'mahasiswa';
            $programId = null;
            if ($kind === 'dosen') {
                $programId = $this->resolveProgramReference(
                    ($columns['prodi_id'][$i] ?? '') ?: null,
                    ($columns['prodi'][$i] ?? '') ?: null,
                    ($columns['fakultas'][$i] ?? '') ?: null
                );
            } elseif (filled($columns['prodi_id'][$i] ?? null) || filled($columns['prodi'][$i] ?? null) || filled($columns['fakultas'][$i] ?? null)) {
                throw ValidationException::withMessages(['penulis_prodi_id' => 'Prodi/fakultas hanya berlaku bagi penulis berjenis dosen.']);
            }

            return [
                'mode' => 'manual', 'nama' => $name, 'jenis_penulis' => $kind,
                'nidn' => ($columns['nidn'][$i] ?? '') ?: null,
                'identitas' => ($columns['identitas'][$i] ?? '') ?: null,
                'program_studi_id' => $programId,
            ];
        }, $columns['nama'], array_keys($columns['nama']));
    }

    /** Older spreadsheet columns may name Prodi/Fakultas; only an existing active match is accepted. */
    private function resolveProgramReference(?string $id, ?string $name, ?string $faculty): ?int
    {
        if (! filled($id) && ! filled($name) && ! filled($faculty)) {
            return null;
        }
        $query = ProgramStudi::query()->where('aktif', true)
            ->whereHas('fakultas', fn ($q) => $q->where('aktif', true));
        if (filled($id)) {
            if (! ctype_digit($id)) {
                throw ValidationException::withMessages(['penulis_prodi_id' => 'ID program studi harus berupa angka pada Data Referensi.']);
            }
            $query->whereKey((int) $id);
        }
        if (filled($name)) {
            $query->whereRaw('LOWER(TRIM(nama)) = ?', [InlineReferenceService::normalized($name)]);
        }
        if (filled($faculty)) {
            $query->whereHas('fakultas', fn ($q) => $q->whereRaw('LOWER(TRIM(nama_fakultas)) = ?', [InlineReferenceService::normalized($faculty)]));
        }
        $matches = $query->limit(2)->pluck('id');
        if ($matches->count() !== 1) {
            throw ValidationException::withMessages(['penulis_prodi_id' => 'Prodi/Fakultas tidak cocok dengan satu referensi aktif. Gunakan penulis_prodi_id dari Data Referensi.']);
        }

        return (int) $matches->first();
    }

    private function candidate(string $type, array $data): Builder
    {
        $query = WorkType::model($type)::whereRaw('LOWER(judul) = ?', [mb_strtolower($data['judul'])])
            ->where(WorkType::year($type), $data[WorkType::year($type)] ?? null);
        if ($type === 'publikasi') {
            if (($data['journal_source'] ?? '') === 'manual') {
                $name = InlineReferenceService::normalized($data['journal_name_manual']);
                $query->where(fn ($q) => $q->whereHas('jurnal', fn ($journal) => $journal->whereRaw('LOWER(TRIM(nama_jurnal)) = ?', [$name]))
                    ->orWhereRaw('LOWER(TRIM(journal_name_manual)) = ?', [$name]));
            } else {
                $query->where('jurnal_id', $data['jurnal_id'] ?? null);
            }
        }
        if ($type === 'buku') {
            if (($data['publisher_source'] ?? '') === 'manual') {
                $query->whereHas('penerbit', fn ($publisher) => $publisher->whereRaw('LOWER(TRIM(nama)) = ?', [InlineReferenceService::normalized($data['publisher_name'])]));
            } else {
                $query->where('penerbit_id', $data['penerbit_id'] ?? null);
            }
        }

        return $query;
    }

    public function commit(ImportBatch $subject, User $actor): int
    {
        Gate::forUser($actor)->authorize('manage-reference');
        abort_unless($subject->user_id === $actor->id, 403);

        return DB::transaction(function () use ($subject, $actor) {
            $batch = ImportBatch::lockForUpdate()->findOrFail($subject->id);
            if ($batch->status === 'committed') {
                return 0;
            }
            if ($batch->created_at->lt(now()->subDay())) {
                throw ValidationException::withMessages(['import' => 'Preview kedaluwarsa. Unggah ulang untuk memeriksa data terkini.']);
            }
            if ($batch->errors || ! $batch->rows) {
                throw ValidationException::withMessages(['import' => 'Perbaiki seluruh error dan unggah ulang sebelum menyimpan.']);
            }
            foreach ($batch->rows as $row) {
                $validated = Validator::make($row['data'], WorkRequest::rulesFor($batch->type))->validate();
                // Master data may have been deactivated since preview.
                if ($batch->type === 'publikasi') {
                    $validated = JournalMetadata::normalize($validated);
                }
                $class = WorkType::model($batch->type);
                $identifier = match ($batch->type) {
                    'publikasi' => $validated['doi'] ?? null, 'buku' => $validated['isbn'] ?? null, default => $validated['nomor_pencatatan'] ?? null
                };
                $existing = $this->candidate($batch->type, $validated);
                if ((! $identifier && $existing->exists()) || ($batch->type === 'hki' && filled($validated['nomor_pencatatan'] ?? null) && $class::where('nomor_pencatatan', $validated['nomor_pencatatan'])->exists())) {
                    throw ValidationException::withMessages(['import' => 'Data duplikat muncul setelah preview pada baris '.$row['line'].'. Unggah ulang.']);
                }
                app(WorkService::class)->save($batch->type, $validated, $actor);
            }
            $batch->update(['status' => 'committed', 'committed_at' => now()]);
            AuditLog::record($batch, $actor, 'import_committed', [], ['count' => count($batch->rows), 'type' => $batch->type]);

            return count($batch->rows);
        });
    }
}

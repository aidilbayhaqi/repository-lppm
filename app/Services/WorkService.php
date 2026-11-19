<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Publikasi;
use App\Models\User;
use App\Support\JournalMetadata;
use App\Support\WorkType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class WorkService
{
    public function save(string $type, array $data, User $actor, ?int $id = null): Model
    {
        return DB::transaction(function () use ($type, $data, $actor, $id) {
            $class = WorkType::model($type);
            $work = $id ? $class::lockForUpdate()->findOrFail($id) : new $class;
            Gate::forUser($actor)->authorize($id ? 'update' : 'create', $id ? $work : $class);
            if ($id) {
                $this->checkVersion($work, $data['lock_version'] ?? -1);
            }
            $old = $id ? $this->snapshot($work) : [];
            $data = app(InlineReferenceService::class)->resolve($type, $data, $actor);
            if ($type === 'publikasi') {
                $data = JournalMetadata::normalize($data);
            }
            $work->fill(Arr::except($data, ['authors', 'citations', 'indexers', 'lock_version', 'journal_source', 'publisher_source', 'publisher_name', 'attachments']));
            if (! $id) {
                $work->forceFill(['created_by' => $actor->id]);
            }
            $work->forceFill(['lock_version' => (int) $work->lock_version + 1]);
            $work->save();
            $pivot = [];
            foreach ($data['authors'] as $i => $author) {
                $row = [($type === 'hki' ? 'urutan_pencipta' : 'urutan_penulis') => $i + 1];
                if ($type !== 'hki') {
                    $row['penulis_korespondensi'] = (bool) ($author['corresponding'] ?? false);
                }
                if ($type === 'publikasi') {
                    $row += ['penulis_pertama' => $i === 0, 'peneliti_utama' => (bool) ($author['principal'] ?? false)];
                }
                $pivot[$author['id']] = $row;
            }
            $work->{WorkType::authors($work)}()->sync($pivot);
            if ($work instanceof Publikasi) {
                $seen = [];
                foreach ($data['citations'] ?? [] as $citation) {
                    $key = implode('|', [$citation['tahun'], $citation['sumber'], $citation['cakupan']]);
                    if (isset($seen[$key])) {
                        throw ValidationException::withMessages(['citations' => 'Kombinasi tahun, sumber, dan cakupan sitasi tidak boleh berulang.']);
                    }
                    $seen[$key] = true;
                }
                $work->sitasi()->delete();
                $work->sitasi()->createMany($data['citations'] ?? []);
                $indexers = [];
                foreach ($data['indexers'] ?? [] as $index) {
                    $indexers[$index['id']] = Arr::only($index, ['tingkat_pengindeksan_id', 'tautan_sumber']);
                }
                $work->pengindeks()->sync($indexers);
            }
            AuditLog::record($work, $actor, $id ? 'updated' : 'created', $old, $this->snapshot($work));

            return $work;
        }, 3);
    }

    public function snapshot(Model $work): array
    {
        $data = $work->only($work->getFillable());
        $data['authors'] = $work->{WorkType::authors($work)}()->get()->map(fn ($a) => ['id' => $a->id, 'pivot' => Arr::except($a->pivot->toArray(), ['created_at', 'updated_at'])])->all();
        if ($work instanceof Publikasi) {
            $data['citations'] = $work->sitasi()->get()->toArray();
            $data['indexers'] = $work->pengindeks()->get()->map(fn ($i) => ['id' => $i->id, 'pivot' => $i->pivot->toArray()])->all();
        }

        return $data;
    }

    public function checkVersion(Model $work, int $expected): void
    {
        if ($work->lock_version !== $expected) {
            throw ValidationException::withMessages(['lock_version' => 'Data sudah berubah. Muat ulang halaman sebelum menyimpan.']);
        }
    }

    public function assertReady(Model $work): void
    {
        if (config('repository.scan_required') && $work->dokumen()->where('scan_status', '!=', 'clean')->exists()) {
            throw ValidationException::withMessages(['workflow' => 'Dokumen belum lolos pemeriksaan keamanan. Hubungi pengelola.']);
        }
        $year = WorkType::year(WorkType::key($work));
        if (! $work->$year || ! $work->{WorkType::authors($work)}()->exists()) {
            throw ValidationException::withMessages(['workflow' => 'Tahun dan minimal satu penulis/pencipta wajib diisi sebelum diajukan atau diterbitkan.']);
        }
        if ($work->{WorkType::authors($work)}()->where('aktif', false)->exists()) {
            throw ValidationException::withMessages(['workflow' => 'Terdapat kontributor nonaktif. Periksa kembali daftar penulis.']);
        }
    }
}

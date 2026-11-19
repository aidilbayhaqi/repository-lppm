<?php

namespace App\Services;

use App\Models\User;
use App\Support\WorkType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SearchService
{
    public function query(string $type, array $filters = [], ?User $actor = null): Builder
    {
        $class = WorkType::model($type);
        $query = $class::query();
        $authors = $type === 'hki' ? 'pencipta' : 'penulis';
        if (! $actor) {
            $query->published();
        } elseif (! $actor->isStaff() && ! ($actor->role === 'verifier' && $type === 'publikasi')) {
            $query->where(fn ($q) => $q->where('created_by', $actor->id)->orWhereHas($authors, fn ($a) => $a->where('penulis.user_id', $actor->id)));
        }
        if (filled($filters['q'] ?? null)) {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($type, $term, $authors) {
                $q->where('judul', 'like', $term)->orWhereHas($authors, fn ($a) => $a->where('nama', 'like', $term));
                if ($type === 'publikasi') {
                    $q->orWhere('journal_name_manual', 'like', $term)->orWhere('abstrak', 'like', $term)->orWhere('doi', 'like', $term)->orWhereHas('jurnal', fn ($j) => $j->where('nama_jurnal', 'like', $term)->orWhere('issn_cetak', 'like', $term)->orWhere('issn_elektronik', 'like', $term));
                } elseif ($type === 'buku') {
                    $q->orWhere('isbn', 'like', $term)->orWhereHas('penerbit', fn ($p) => $p->where('nama', 'like', $term));
                } else {
                    $q->orWhere('nomor_pencatatan', 'like', $term)->orWhere('nomor_permohonan', 'like', $term);
                }
            });
        }
        if ($year = $filters['year'] ?? null) {
            $query->where(WorkType::year($type), $year);
        }
        if ($program = $filters['program_studi_id'] ?? null) {
            $type === 'publikasi' ? $query->where('program_studi_id', $program) : $query->whereHas($authors.'.dosen', fn ($q) => $q->where('program_studi_id', $program));
        }
        if ($faculty = $filters['fakultas_id'] ?? null) {
            $type === 'publikasi' ? $query->where('fakultas_id', $faculty) : $query->whereHas($authors.'.dosen.programStudi', fn ($q) => $q->where('fakultas_id', $faculty));
        }
        if ($lecturer = $filters['dosen_id'] ?? null) {
            $query->whereHas($authors, fn ($q) => $q->where('dosen_id', $lecturer));
        }
        if ($author = $filters['penulis_id'] ?? null) {
            $query->whereHas($authors, fn ($q) => $q->where('penulis.id', $author));
        }
        if ($journal = $filters['jurnal_id'] ?? null) {
            $type === 'publikasi' ? $query->where('jurnal_id', $journal) : $query->whereRaw('1 = 0');
        }
        if ($index = $filters['pengindeks_id'] ?? null) {
            $type === 'publikasi' ? $query->whereHas('pengindeks', fn ($q) => $q->where('pengindeks.id', $index)) : $query->whereRaw('1 = 0');
        }
        foreach (['publication_type', 'sinta_rank', 'scopus_quartile', 'scopus_indexed'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $type === 'publikasi' ? $query->where($field, $filters[$field]) : $query->whereRaw('1 = 0');
            }
        }
        if ($actor && filled($filters['visibility'] ?? null)) {
            $query->where('public_visibility', $filters['visibility']);
        }
        if ($actor && filled($filters['status'] ?? null)) {
            $status = $filters['status'];
            if ($status === 'archived') {
                $query->whereNotNull('archived_at');
            } else {
                $query->whereNull('archived_at');
                $query->where($type === 'publikasi' ? 'repository_status' : 'status', $type === 'publikasi' ? $status : ($status === 'published' ? 'dipublikasikan' : 'draft'));
            }
        }

        return $query;
    }

    public function cardColumns(string $type): array
    {
        return ['id', 'judul', WorkType::year($type), ...match ($type) {
            'publikasi' => ['jurnal_id', 'journal_name_manual', 'publication_type', 'sinta_rank', 'scopus_indexed', 'scopus_quartile'],
            'buku' => ['penerbit_id'],
            'hki' => ['jenis'],
        }];
    }

    public function publicResults(array $filters): LengthAwarePaginator
    {
        $union = null;
        $types = filled($filters['type'] ?? null) ? [$filters['type']] : array_keys(WorkType::TYPES);
        // Direct pagination for one work type avoids the redundant ID-query + re-fetch.
        if (count($types) === 1) {
            $type = $types[0];
            $relations = [$type === 'hki' ? 'pencipta' : 'penulis', 'cover'];
            if ($type === 'publikasi') {
                $relations[] = 'jurnal';
            }
            if ($type === 'buku') {
                $relations[] = 'penerbit';
            }
            $query = $this->query($type, $filters)
                ->select($this->cardColumns($type))
                ->with($relations);
            match ($filters['sort'] ?? 'newest') {
                'oldest' => $query->orderBy(WorkType::year($type)),
                'title' => $query->orderBy('judul'),
                default => $query->orderByDesc(WorkType::year($type)),
            };
            return $query->orderBy('judul')->orderBy('id')
                ->paginate(12)->withQueryString();
        }
        foreach ($types as $type) {
            $q = $this->query($type, $filters)->select(['id', 'judul', WorkType::year($type).' as year'])->selectRaw('? as type', [$type]);
            $union = $union ? $union->unionAll($q) : $q;
        }
        $ordered = DB::query()->fromSub($union, 'works');
        match ($filters['sort'] ?? 'newest') {
            'oldest' => $ordered->orderBy('year'),
            'title' => $ordered->orderBy('judul'),
            default => $ordered->orderByDesc('year'),
        };
        $results = $ordered->orderBy('judul')->orderBy('type')->orderBy('id')->paginate(12)->withQueryString();
        $models = [];
        foreach ($types as $type) {
            $ids = $results->getCollection()->where('type', $type)->pluck('id');
            $relations = [$type === 'hki' ? 'pencipta' : 'penulis', 'cover'];
            if ($type === 'publikasi') {
                $relations[] = 'jurnal';
            }
            if ($type === 'buku') {
                $relations[] = 'penerbit';
            }
            $models[$type] = $ids->isEmpty() ? collect() : $this->query($type, $filters)->select($this->cardColumns($type))->with($relations)->whereIn('id', $ids)->get()->keyBy('id');
        }
        $results->setCollection($results->getCollection()->map(fn ($row) => $models[$row->type]->get($row->id))->filter()->values());

        return $results;
    }
}

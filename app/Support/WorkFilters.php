<?php

namespace App\Support;

use App\Enums\RepositoryStatus;
use App\Models\Dosen;
use App\Models\Fakultas;
use App\Models\Pengindeks;
use App\Models\ProgramStudi;
use Illuminate\Validation\Rule;

final class WorkFilters
{
    public static function rules(): array
    {
        return [
            'q' => 'nullable|string|max:150', 'type' => ['nullable', Rule::in(array_keys(WorkType::TYPES))],
            'status' => ['nullable', Rule::in(array_column(RepositoryStatus::cases(), 'value'))],
            'year' => 'nullable|integer|min:1901|max:2100',
            'program_studi_id' => 'nullable|integer|exists:program_studi,id', 'fakultas_id' => 'nullable|integer|exists:fakultas,id',
            'dosen_id' => 'nullable|integer|exists:dosen,id', 'pengindeks_id' => 'nullable|integer|exists:pengindeks,id',
            'publication_type' => ['nullable', Rule::in(array_keys(JournalMetadata::TYPES))],
            'sinta_rank' => 'nullable|integer|between:1,6', 'scopus_quartile' => ['nullable', Rule::in(['Q1', 'Q2', 'Q3', 'Q4'])],
            'scopus_indexed' => 'nullable|in:1', 'visibility' => ['nullable', Rule::in(['public', 'internal', 'private', 'restricted'])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'title', 'updated'])],
        ];
    }

    public static function options(): array
    {
        return [
            'programs' => LookupOptions::initial(ProgramStudi::active()->select(['id', 'nama']), 'nama', [request('program_studi_id')]),
            'faculties' => LookupOptions::initial(Fakultas::active()->select(['id', 'nama_fakultas']), 'nama_fakultas', [request('fakultas_id')]),
            'indexers' => LookupOptions::initial(Pengindeks::active()->select(['id', 'nama']), 'nama', [request('pengindeks_id')]),
            'lecturers' => LookupOptions::initial(Dosen::active()->select(['id', 'nama']), 'nama', [request('dosen_id')]),
        ];
    }
}

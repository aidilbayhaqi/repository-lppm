<?php

namespace App\Support;

use App\Models\Fakultas;
use App\Models\Pengindeks;
use App\Models\ProgramStudi;
use App\Models\TingkatPengindeksan;
use Illuminate\Validation\ValidationException;

final class JournalMetadata
{
    public const TYPES = ['artikel_jurnal' => 'Artikel jurnal', 'prosiding' => 'Prosiding', 'lainnya' => 'Karya ilmiah lainnya'];

    public static function ranks(array $indexers, bool $strict = true): array
    {
        $indices = Pengindeks::whereIn('id', array_column($indexers, 'id'))->pluck('nama', 'id');
        $levels = TingkatPengindeksan::whereIn('id', array_filter(array_column($indexers, 'tingkat_pengindeksan_id')))->get()->keyBy('id');
        $result = ['sinta_rank' => null, 'scopus_quartile' => null, 'scopus_indexed' => false];
        foreach ($indexers as $i => $row) {
            $name = mb_strtolower(trim($indices[$row['id']] ?? ''));
            $level = $levels[$row['tingkat_pengindeksan_id'] ?? null] ?? null;
            $value = preg_replace('/[^a-z0-9]/', '', mb_strtolower($level?->kode ?? ''));
            $label = preg_replace('/[^a-z0-9]/', '', mb_strtolower($level?->nama ?? ''));
            if ($name === 'sinta') {
                $matched = preg_match('/^(?:s|sinta)([1-6])$/', $value, $matches) || preg_match('/^sinta([1-6])$/', $label, $matches);
                if ($level && ! $matched && $strict) {
                    throw ValidationException::withMessages(["indexers.$i.tingkat_pengindeksan_id" => 'Pilih tingkat SINTA 1 sampai 6 untuk pengindeks SINTA.']);
                }
                $result['sinta_rank'] = $matched ? (int) $matches[1] : null;
            }
            if ($name === 'scopus') {
                $result['scopus_indexed'] = true;
                $matched = preg_match('/^(?:scopus)?q([1-4])$/', $value, $matches) || preg_match('/^(?:scopus)?q([1-4])$/', $label, $matches);
                if ($level && ! $matched && $strict) {
                    throw ValidationException::withMessages(["indexers.$i.tingkat_pengindeksan_id" => 'Pilih Q1 sampai Q4 untuk Scopus, atau kosongkan jika kuartil belum diketahui.']);
                }
                $result['scopus_quartile'] = $matched ? 'Q'.$matches[1] : null;
            }
        }

        return $result;
    }

    public static function normalize(array $data): array
    {
        foreach (['faculty_name', 'program_name'] as $field) {
            if (filled($data[$field] ?? null)) {
                throw ValidationException::withMessages([$field => 'Pilih dari Data Referensi. Penambahan manual tidak diperbolehkan.']);
            }
        }
        $facultyId = $data['fakultas_id'] ?? null;
        $programId = $data['program_studi_id'] ?? null;
        if (filled($facultyId) && ! Fakultas::active()->whereKey($facultyId)->exists()) {
            throw ValidationException::withMessages(['fakultas_id' => 'Fakultas tidak tersedia. Pilih referensi yang aktif.']);
        }
        if (filled($programId)) {
            $program = ProgramStudi::active()->whereHas('fakultas', fn ($q) => $q->where('aktif', true))->find($programId);
            if (! $program) {
                throw ValidationException::withMessages(['program_studi_id' => 'Prodi atau fakultasnya tidak aktif. Periksa Data Referensi.']);
            }
            if (filled($facultyId) && (int) $facultyId !== (int) $program->fakultas_id) {
                throw ValidationException::withMessages(['program_studi_id' => 'Program studi tidak termasuk fakultas yang dipilih.']);
            }
            $data['fakultas_id'] = $program->fakultas_id;
        }

        if (($data['journal_source'] ?? 'reference') === 'manual') {
            $data['jurnal_id'] = null;
        } elseif (array_key_exists('journal_source', $data)) {
            $data['journal_name_manual'] = null;
        }
        if (($data['indexing_mode'] ?? 'reference') === 'manual') {
            $data['indexers'] = [];
            $data['scopus_indexed'] = (bool) ($data['scopus_indexed'] ?? false);
            if (! $data['scopus_indexed']) {
                $data['scopus_quartile'] = null;
            }
        } else {
            $data = array_replace($data, self::ranks($data['indexers'] ?? []));
        }

        return $data;
    }
}

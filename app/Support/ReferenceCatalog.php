<?php

namespace App\Support;

use App\Models\AfiliasiPenulis;
use App\Models\Dosen;
use App\Models\Fakultas;
use App\Models\Jurnal;
use App\Models\Penerbit;
use App\Models\Pengindeks;
use App\Models\Penulis;
use App\Models\ProgramStudi;
use App\Models\TingkatPengindeksan;

class ReferenceCatalog
{
    public static function all(): array
    {
        $active = ['Aktif', 'boolean', 'required|boolean'];
        $text = fn ($label, $max = 150) => [$label, 'text', "nullable|string|max:$max"];
        $required = fn ($label, $max = 150) => [$label, 'text', "required|string|max:$max"];
        $ref = fn ($label, $model, $column, $table, $required = true) => [$label, 'select', ($required ? 'required' : 'nullable')."|integer|exists:$table,id", 'ref' => [$model, $column]];
        $select = fn ($label, $values) => [$label, 'select', 'required|in:'.implode(',', array_keys($values)), 'options' => $values];

        return [
            'fakultas' => ['label' => 'Fakultas', 'model' => Fakultas::class, 'title' => 'nama_fakultas', 'fields' => [
                'kode' => $required('Kode', 20) + ['unique' => 'fakultas'], 'nama_fakultas' => $required('Nama fakultas', 100), 'deskripsi' => ['Deskripsi', 'textarea', 'nullable|string|max:5000'], 'aktif' => $active]],
            'program-studi' => ['label' => 'Program studi', 'model' => ProgramStudi::class, 'title' => 'nama', 'fields' => [
                'fakultas_id' => $ref('Fakultas', Fakultas::class, 'nama_fakultas', 'fakultas'), 'kode' => $required('Kode', 20) + ['unique' => 'program_studi'], 'nama' => $required('Nama', 100), 'jenjang_pendidikan' => $text('Jenjang', 50), 'deskripsi' => ['Deskripsi', 'textarea', 'nullable|string|max:5000'], 'aktif' => $active]],
            'dosen' => ['label' => 'Dosen', 'model' => Dosen::class, 'title' => 'nama', 'fields' => [
                'program_studi_id' => $ref('Program studi', ProgramStudi::class, 'nama', 'program_studi'), 'nidn' => $text('NIDN', 20) + ['unique' => 'dosen'], 'nama' => $required('Nama'), 'gelar' => $text('Gelar', 100), 'email' => ['Email institusi', 'email', 'nullable|email|max:150'], 'orcid_id' => $text('ORCID', 50), 'scopus_id' => $text('Scopus ID', 50), 'google_scholar_id' => $text('Google Scholar ID', 100), 'aktif' => $active]],
            'penulis' => ['label' => 'Penulis', 'model' => Penulis::class, 'title' => 'nama', 'fields' => [
                'nama' => $required('Nama'), 'jenis_penulis' => $select('Jenis penulis', ['dosen' => 'Dosen', 'mahasiswa' => 'Mahasiswa', 'eksternal' => 'Eksternal']),
                'dosen_id' => $ref('Dosen internal', Dosen::class, 'nama', 'dosen', false),
                'identitas' => $text('NIM / identitas internal', 100), 'orcid_id' => $text('ORCID', 50), 'scopus_id' => $text('Scopus ID', 50), 'google_scholar_id' => $text('Google Scholar ID', 100), 'aktif' => $active]],
            'afiliasi' => ['label' => 'Afiliasi penulis', 'model' => AfiliasiPenulis::class, 'title' => 'institusi', 'fields' => [
                'penulis_id' => $ref('Penulis', Penulis::class, 'nama', 'penulis'), 'institusi' => $required('Institusi'), 'departemen' => $text('Departemen'), 'afiliasi_utama' => ['Afiliasi utama', 'boolean', 'required|boolean']]],
            'penerbit' => ['label' => 'Penerbit', 'model' => Penerbit::class, 'title' => 'nama', 'fields' => [
                'nama' => $required('Nama'), 'kode' => $text('Kode', 50) + ['unique' => 'penerbit'], 'website' => ['Website', 'url', 'nullable|url:http,https|max:255'], 'email' => ['Email', 'email', 'nullable|email|max:150'], 'telepon' => $text('Telepon', 50), 'alamat' => ['Alamat', 'textarea', 'nullable|string|max:2000'], 'kota' => $text('Kota', 100), 'negara' => $required('Negara', 100), 'aktif' => $active]],
            'jurnal' => ['label' => 'Jurnal', 'model' => Jurnal::class, 'title' => 'nama_jurnal', 'fields' => [
                'nama_jurnal' => $required('Nama jurnal', 200), 'penerbit_id' => $ref('Penerbit', Penerbit::class, 'nama', 'penerbit', false), 'issn_cetak' => $text('ISSN cetak', 20), 'issn_elektronik' => $text('ISSN elektronik', 20), 'website' => ['Website', 'url', 'nullable|url:http,https|max:255'], 'aktif' => $active]],
            'pengindeks' => ['label' => 'Pengindeks', 'model' => Pengindeks::class, 'title' => 'nama', 'fields' => [
                'nama' => $required('Nama', 100), 'kategori' => $select('Kategori', ['nasional' => 'Nasional', 'internasional' => 'Internasional', 'lainnya' => 'Lainnya']), 'website' => ['Website', 'url', 'nullable|url:http,https|max:255'], 'deskripsi' => ['Deskripsi', 'textarea', 'nullable|string|max:5000'], 'aktif' => $active]],
            'tingkat-pengindeksan' => ['label' => 'Tingkat pengindeksan', 'model' => TingkatPengindeksan::class, 'title' => 'nama', 'fields' => [
                'kode' => $required('Kode', 30) + ['unique' => 'tingkat_pengindeksan'], 'nama' => $required('Nama'), 'kategori' => $select('Kategori', ['nasional' => 'Nasional', 'internasional' => 'Internasional', 'lainnya' => 'Lainnya']), 'urutan' => ['Urutan', 'number', 'nullable|integer|min:0|max:10000'], 'deskripsi' => ['Deskripsi', 'textarea', 'nullable|string|max:5000'], 'aktif' => $active]],
        ];
    }

    public static function get(string $entity): array
    {
        return self::all()[$entity] ?? abort(404);
    }

    public static function options(array $field, mixed $selected = null): array
    {
        if (isset($field['options'])) {
            return $field['options'];
        }
        if (isset($field['ref'])) {
            [$class,$label] = $field['ref'];

            return LookupOptions::initial($class::query(), $label, [$selected])->pluck($label, 'id')->all();
        }

        return [];
    }
}

<?php

namespace App\Http\Requests;

use App\Support\DocumentTypes;
use App\Support\JournalMetadata;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class WorkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('doi')) {
            $this->merge(['doi' => self::normalizeDoi($this->input('doi'))]);
        }
        if ($this->has('isbn') && (is_string($this->input('isbn')) || $this->input('isbn') === null)) {
            $this->merge(['isbn' => preg_replace('/[\s-]+/', '', strtoupper($this->input('isbn') ?? '')) ?: null]);
        }
        if (is_array($this->input('authors'))) {
            $this->merge(['authors' => array_values($this->input('authors'))]);
        }
        foreach (['citations' => 'tahun', 'indexers' => 'id'] as $key => $field) {
            if (is_array($this->input($key))) {
                $this->merge([$key => array_values(array_filter($this->input($key), fn ($row) => is_array($row) && filled($row[$field] ?? null)))]);
            }
        }
    }

    public function after(): array
    {
        return [function ($validator) {
            $bytes = 0;
            foreach ($this->file('attachments', []) as $attachment) {
                if (is_array($attachment) && ($attachment['file'] ?? null) instanceof UploadedFile && $attachment['file']->isValid()) {
                    $bytes += $attachment['file']->getSize();
                }
            }
            if ($bytes > 20 * 1024 * 1024) {
                $validator->errors()->add('attachments', 'Total seluruh file maksimal 20 MB. Kurangi ukuran atau unggah sisanya dari halaman detail.');
            }
        }];
    }

    public static function normalizeDoi(mixed $value): mixed
    {
        if ($value !== null && ! is_string($value)) {
            return $value;
        }

        return strtolower(trim(preg_replace('~^(?:https?://(?:dx\.)?doi\.org/|doi:\s*)~i', '', trim($value ?? '')))) ?: null;
    }

    public function rules(): array
    {
        return self::rulesFor((string) $this->route('type'), $this->route('id') ? (int) $this->route('id') : null, $this->isMethod('put'));
    }

    public static function rulesFor(string $type, ?int $id = null, bool $updating = false): array
    {
        $common = [
            'judul' => ['required', 'string', 'max:500'],
            'tautan_eksternal' => ['nullable', 'url:http,https', 'max:500'],
            'keterangan' => ['nullable', 'string', 'max:10000'],
            'public_visibility' => ['required', Rule::in(['public', 'internal', 'private', 'restricted'])],
            'lock_version' => [$updating ? 'required' : 'nullable', 'integer', 'min:0'],
            'authors' => ['required', 'array', 'min:1', 'max:50'],
            'authors.*' => ['array:id,mode,nama,jenis_penulis,nidn,identitas,program_studi_id,program_name,faculty_name,corresponding,principal'],
            'authors.*.mode' => ['nullable', Rule::in(['reference', 'manual'])],
            'authors.*.nama' => ['exclude_if:authors.*.mode,reference', 'required_if:authors.*.mode,manual', 'required_without:authors.*.id', 'nullable', 'string', 'max:150'],
            'authors.*.jenis_penulis' => ['exclude_if:authors.*.mode,reference', 'required_if:authors.*.mode,manual', 'required_without:authors.*.id', 'nullable', Rule::in(['dosen', 'mahasiswa', 'eksternal'])],
            'authors.*.nidn' => ['required_if:authors.*.jenis_penulis,dosen', 'nullable', 'string', 'regex:/^\d{10}$/'],
            'authors.*.identitas' => ['nullable', 'string', 'max:100'],
            'authors.*.program_studi_id' => ['nullable', 'integer', Rule::exists('program_studi', 'id')->where('aktif', true)],
            'authors.*.program_name' => ['prohibited'],
            'authors.*.faculty_name' => ['prohibited'],
            'authors.*.id' => ['required_if:authors.*.mode,reference', 'nullable', 'integer', 'distinct', Rule::exists('penulis', 'id')->where('aktif', true)],
            'authors.*.corresponding' => ['nullable', 'boolean'],
            'authors.*.principal' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['array:file,jenis_dokumen,publik'],
            'attachments.*.file' => ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'extensions:pdf', 'max:20480'],
            'attachments.*.jenis_dokumen' => ['required_with:attachments.*.file', Rule::in(array_keys(DocumentTypes::OPTIONS))],
            'attachments.*.publik' => ['nullable', 'boolean'],
        ];
        $year = ['nullable', 'integer', 'min:1901', 'max:'.(date('Y') + 1)];

        return $common + match ($type) {
            'publikasi' => [
                // Academic units must be existing ACTIVE reference records; names cannot be created here.
                'fakultas_id' => ['nullable', 'integer', Rule::exists('fakultas', 'id')->where('aktif', true)],
                'program_studi_id' => ['nullable', 'integer', Rule::exists('program_studi', 'id')->where('aktif', true)],
                'faculty_name' => ['prohibited'],
                'program_name' => ['prohibited'],
                'isbn' => ['nullable', 'string', 'regex:/^(?:\d{9}[\dX]|\d{13})$/'],
                'issn_cetak' => ['nullable', 'string', 'regex:/^\d{4}-?\d{3}[\dXx]$/'],
                'issn_elektronik' => ['nullable', 'string', 'regex:/^\d{4}-?\d{3}[\dXx]$/'],
                'publication_type' => ['nullable', Rule::in(array_keys(JournalMetadata::TYPES))],
                'journal_source' => ['nullable', Rule::in(['reference', 'manual'])],
                'journal_name_manual' => ['required_if:journal_source,manual', 'nullable', 'string', 'max:200'],
                'indexing_mode' => ['nullable', Rule::in(['reference', 'manual'])],
                'sinta_rank' => ['nullable', 'integer', 'between:1,6'],
                'scopus_indexed' => ['nullable', 'boolean'],
                'scopus_quartile' => ['nullable', Rule::in(['Q1', 'Q2', 'Q3', 'Q4'])],
                'indexing_year' => $year,
                'indexing_source' => ['nullable', 'url:http,https', 'max:500'],
                'abstrak' => ['nullable', 'string', 'max:20000'],
                'doi' => ['nullable', 'string', 'max:150', 'regex:~^10\.\d{4,9}/\S+$~', Rule::unique('publikasi', 'doi')->ignore($id)],
                'jurnal_id' => ['nullable', 'integer', Rule::exists('jurnal', 'id')],
                'tahun_publikasi' => $year, 'tanggal_publikasi' => ['nullable', 'date'],
                'academic_status' => ['nullable', Rule::in(['draft', 'submitted', 'under_review', 'accepted', 'published', 'rejected'])],
                'volume' => ['nullable', 'string', 'max:50'], 'nomor' => ['nullable', 'string', 'max:50'], 'halaman' => ['nullable', 'string', 'max:50'],
                'tautan_google_scholar' => ['nullable', 'url:http,https', 'max:500'],
                'spmi' => ['nullable', 'string', 'max:100'], 'tahun_dasar' => $year, 'klasifikasi' => ['nullable', 'string', 'max:150'],
                'biaya' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
                'citations' => ['nullable', 'array', 'max:100'],
                'citations.*' => ['array:tahun,jumlah_sitasi,sumber,cakupan'],
                'citations.*.tahun' => ['required', 'integer', 'min:1901', 'max:'.date('Y')],
                'citations.*.jumlah_sitasi' => ['required', 'integer', 'min:0', 'max:2147483647'],
                'citations.*.sumber' => ['required', Rule::in(['google_scholar', 'scopus', 'sinta', 'lainnya'])],
                'citations.*.cakupan' => ['required', Rule::in(['umum', 'nasional', 'internasional'])],
                'indexers' => ['nullable', 'array', 'max:30'],
                'indexers.*' => ['array:id,tingkat_pengindeksan_id,tautan_sumber'],
                'indexers.*.id' => ['required', 'integer', 'distinct', Rule::exists('pengindeks', 'id')],
                'indexers.*.tingkat_pengindeksan_id' => ['nullable', 'integer', Rule::exists('tingkat_pengindeksan', 'id')],
                'indexers.*.tautan_sumber' => ['nullable', 'url:http,https', 'max:500'],
            ],
            'buku' => [
                'publisher_source' => ['nullable', Rule::in(['reference', 'manual'])],
                'publisher_name' => ['required_if:publisher_source,manual', 'nullable', 'string', 'max:150'],
                'penerbit_id' => ['nullable', 'integer', Rule::exists('penerbit', 'id')],
                'isbn' => ['nullable', 'string', 'regex:/^(?:\d{9}[\dX]|\d{13})$/', Rule::unique('buku', 'isbn')->ignore($id)],
                'tahun_publikasi' => $year, 'tanggal_publikasi' => ['nullable', 'date'],
                'jumlah_halaman' => ['nullable', 'integer', 'min:1', 'max:100000'], 'kategori' => ['nullable', 'string', 'max:100'],
            ],
            'hki' => [
                'jenis' => ['required', 'string', 'max:100'], 'tahun' => $year,
                'tanggal_permohonan' => ['nullable', 'date'], 'tanggal_pencatatan' => ['nullable', 'date', 'after_or_equal:tanggal_permohonan'],
                'nomor_permohonan' => ['nullable', 'string', 'max:100'], 'nomor_pencatatan' => ['nullable', 'string', 'max:100'], 'periode' => ['nullable', 'string', 'max:50'],
            ],default => abort(404),
        };
    }
}

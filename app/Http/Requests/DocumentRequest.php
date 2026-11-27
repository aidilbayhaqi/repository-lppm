<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf',
                'extensions:pdf',
                'max:20480',
            ],

            'publik' => [
                'required',
                'boolean',
            ],

            'jenis_dokumen' => [
                'required',
                Rule::in([
                    'dokumen_utama',
                    'cover',
                    'sertifikat',
                    'lampiran',
                    'pendukung',
                    'lainnya',
                ]),
            ],

            'lock_version' => [
                'required',
                'integer',
                'min:0',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Silakan pilih file PDF yang ingin diunggah.',

            'file.file' => 'File yang dipilih tidak valid.',

            'file.mimes' => 'Hanya file PDF yang diperbolehkan.',

            'file.mimetypes' => 'Konten file harus berupa dokumen PDF.',

            'file.extensions' => 'Ekstensi file harus .pdf.',

            'file.max' => 'Ukuran file PDF maksimal 20 MB.',

            'file.uploaded' => 'Upload gagal. Periksa batas ukuran file pada server PHP.',

            'publik.required' => 'Pilih pengaturan akses dokumen.',

            'jenis_dokumen.required' => 'Pilih jenis dokumen.',

            'jenis_dokumen.in' => 'Jenis dokumen yang dipilih tidak tersedia.',

            'lock_version.required' => 'Versi data tidak ditemukan. Muat ulang halaman.',

            'lock_version.integer' => 'Versi data tidak valid.',
        ];
    }
}

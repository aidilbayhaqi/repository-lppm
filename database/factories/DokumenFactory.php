<?php

namespace Database\Factories;

use App\Models\Dokumen;
use App\Models\Publikasi;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @extends Factory<Dokumen>
 */
class DokumenFactory extends Factory
{
    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'pemilik_dokumen_type' => Publikasi::class,

            'pemilik_dokumen_id' => Publikasi::factory(),

            'nama_file' => "{$uuid}.pdf",

            'nama_file_asli' => 'artikel.pdf',

            'path_file' => "documents/{$uuid}.pdf",

            'ukuran_file' => fake()->numberBetween(
                100000,
                5000000
            ),

            'mime_type' => 'application/pdf',

            'jenis_dokumen' => fake()->randomElement([
                'dokumen_utama',
                'cover',
                'sertifikat',
                'lampiran',
                'pendukung',
                'lainnya',
            ]),

            'publik' => fake()->boolean(70),
        ];
    }

    public function forOwner(Model $owner): static
    {
        return $this->state([
            'pemilik_dokumen_type' => $owner->getMorphClass(),
            'pemilik_dokumen_id' => $owner->getKey(),
        ]);
    }

    public function public(): static
    {
        return $this->state([
            'publik' => true,
        ]);
    }
}

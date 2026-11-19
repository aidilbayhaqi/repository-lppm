<?php

namespace Database\Factories;

use App\Models\Jurnal;
use App\Models\Publikasi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Publikasi>
 */
class PublikasiFactory extends Factory
{
    public function definition(): array
    {
        $tahun = fake()->numberBetween(2020, 2025);

        $tanggalPublikasi = fake()->dateTimeBetween(
            "{$tahun}-01-01",
            "{$tahun}-12-31"
        );

        $dipublikasikan = fake()->boolean(70);

        return [
            'jurnal_id' => Jurnal::factory(),

            'judul' => fake()->sentence(
                fake()->numberBetween(6, 14)
            ),

            'abstrak' => fake()->paragraphs(
                2,
                true
            ),

            'doi' => $dipublikasikan
                ? fake()->unique()->numerify('10.####/#####')
                : null,

            'tahun_publikasi' => $dipublikasikan
                ? $tahun
                : null,

            'tanggal_publikasi' => $dipublikasikan
                ? $tanggalPublikasi
                : null,

            'volume' => fake()->numberBetween(1, 20),

            'nomor' => fake()->numberBetween(1, 12),

            'halaman' => fake()->numberBetween(1, 10)
                .'-'
                .fake()->numberBetween(11, 30),

            'tautan_eksternal' => fake()->optional()->url(),

            'tautan_google_scholar' => $dipublikasikan
                ? fake()->optional(0.7)->url()
                : null,

            'spmi' => fake()->randomElement([
                'Internal',
                'Nasional',
                'Internasional',
            ]),

            'tahun_dasar' => $tahun,

            'klasifikasi' => fake()->randomElement([
                'Penelitian',
                'Pengabdian',
                'Review',
            ]),

            'biaya' => fake()->randomFloat(
                2,
                1000000,
                10000000
            ),

            'keterangan' => fake()->optional()->sentence(),

            'public_visibility' => 'public',
            'repository_status' => $dipublikasikan
                ? 'published'
                : 'draft',
        ];
    }

    public function draft(): static
    {
        return $this->state([
            'doi' => null,
            'tahun_publikasi' => null,
            'tanggal_publikasi' => null,
            'repository_status' => 'draft',
        ]);
    }

    public function published(): static
    {
        return $this->state([
            'repository_status' => 'published',
            'public_visibility' => 'public',
        ]);
    }
}

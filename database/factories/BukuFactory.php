<?php

namespace Database\Factories;

use App\Models\Buku;
use App\Models\Penerbit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Buku>
 */
class BukuFactory extends Factory
{
    public function definition(): array
    {
        $tahun = fake()->numberBetween(2020, 2025);

        return [
            'penerbit_id' => Penerbit::factory(),

            'judul' => fake()->sentence(
                fake()->numberBetween(5, 12)
            ),

            'isbn' => fake()->unique()->numerify(
                '978##########'
            ),

            'tahun_publikasi' => $tahun,

            'tanggal_publikasi' => fake()->dateTimeBetween(
                "{$tahun}-01-01",
                "{$tahun}-12-31"
            ),

            'jumlah_halaman' => fake()->numberBetween(
                80,
                500
            ),

            'kategori' => fake()->randomElement([
                'Buku Referensi',
                'Monograf',
                'Buku Ajar',
                'Buku Umum',
            ]),

            'tautan_eksternal' => fake()->optional()->url(),

            'keterangan' => fake()->optional()->sentence(),

            'status' => fake()->randomElement([
                'draft',
                'dipublikasikan',
            ]),
        ];
    }

    public function published(): static
    {
        return $this->state([
            'status' => 'dipublikasikan',
        ]);
    }

    public function draft(): static
    {
        return $this->state([
            'status' => 'draft',
        ]);
    }
}
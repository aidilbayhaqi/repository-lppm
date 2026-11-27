<?php

namespace Database\Factories;

use App\Models\TingkatPengindeksan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TingkatPengindeksan>
 */
class TingkatPengindeksanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kode' => fake()->unique()->lexify('IDX-???'),

            'nama' => fake()->randomElement([
                'SINTA 1',
                'SINTA 2',
                'SINTA 3',
                'Scopus Q1',
                'Scopus Q2',
            ]),

            'kategori' => fake()->randomElement([
                'nasional',
                'internasional',
                'lainnya',
            ]),

            'urutan' => fake()->numberBetween(1, 5),

            'deskripsi' => fake()->sentence(),

            'aktif' => true,
        ];
    }
}

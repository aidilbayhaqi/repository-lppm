<?php

namespace Database\Factories;

use App\Models\Pengindeks;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pengindeks>
 */
class PengindeksFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => fake()->randomElement([
                'Google Scholar',
                'Scopus',
                'SINTA',
                'DOAJ',
            ]),

            'kategori' => fake()->randomElement([
                'nasional',
                'internasional',
                'lainnya',
            ]),

            'website' => fake()->optional()->url(),

            'deskripsi' => fake()->optional()->sentence(),

            'aktif' => true,
        ];
    }
}

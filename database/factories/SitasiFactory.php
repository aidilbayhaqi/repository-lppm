<?php

namespace Database\Factories;

use App\Models\Publikasi;
use App\Models\Sitasi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sitasi>
 */
class SitasiFactory extends Factory
{
    public function definition(): array
    {
        return [
            'publikasi_id' => Publikasi::factory(),

            'tahun' => fake()->numberBetween(
                2021,
                2026
            ),

            'sumber' => fake()->randomElement([
                'google_scholar',
                'scopus',
                'sinta',
                'lainnya',
            ]),

            'cakupan' => fake()->randomElement([
                'umum',
                'nasional',
                'internasional',
            ]),

            'jumlah_sitasi' => fake()->numberBetween(
                0,
                200
            ),
        ];
    }
}
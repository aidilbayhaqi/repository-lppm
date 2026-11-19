<?php

namespace Database\Factories;

use App\Models\AfiliasiPenulis;
use App\Models\Penulis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AfiliasiPenulis>
 */
class AfiliasiPenulisFactory extends Factory
{
    public function definition(): array
    {
        return [
            'penulis_id' => Penulis::factory(),

            'institusi' => fake()->randomElement([
                'Universitas ABC',
                'Institut Teknologi Nusantara',
                'Universitas Indonesia',
                'Universitas Gadjah Mada',
                'Universitas Diponegoro',
            ]),

            'departemen' => fake()->optional()->randomElement([
                'Fakultas Teknik',
                'Fakultas Ekonomi',
                'Fakultas Hukum',
                'Fakultas Sains',
                'Program Studi Ilmu Komputer',
            ]),

            'afiliasi_utama' => true,
        ];
    }
}
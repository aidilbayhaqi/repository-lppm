<?php

namespace Database\Factories;

use App\Models\Fakultas;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fakultas>
 */
class FakultasFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kode' => fake()->unique()->lexify('FT??'),

            'nama_fakultas' => fake()->randomElement([
                'Fakultas Manajemen',
                'Fakultas Hukum',
                'Fakultas FTIA',

            ]),

            'deskripsi' => fake()->sentence(),

            'aktif' => true,
        ];
    }
}

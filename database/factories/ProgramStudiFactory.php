<?php

namespace Database\Factories;

use App\Models\Fakultas;
use App\Models\ProgramStudi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramStudi>
 */
class ProgramStudiFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fakultas_id' => Fakultas::factory(),

            'kode' => fake()->unique()->lexify('PS??'),

            'nama' => fake()->randomElement([
                'Ilmu Komputer',
                'Sistem Teknologi Informasi',
                'Manajemen',
                'Hukum',
                'Aktuaria',
            ]),

            'jenjang_pendidikan' => fake()->randomElement([
                'S1',
                'S2',
            ]),

            'deskripsi' => fake()->sentence(),

            'aktif' => true,
        ];
    }
}
<?php

namespace Database\Factories;

use App\Models\Jurnal;
use App\Models\Penerbit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Jurnal>
 */
class JurnalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'penerbit_id' => Penerbit::factory(),

            'nama_jurnal' => fake()->randomElement([
                'Jurnal Teknologi Informasi',
                'Jurnal Sistem Informasi',
                'Jurnal Rekayasa Perangkat Lunak',
                'Jurnal Sains dan Teknologi',
                'Jurnal Ekonomi dan Bisnis',
                'Jurnal Hukum Indonesia',
            ]),

            'issn_cetak' => fake()
                ->optional(0.7)
                ->numerify('####-####'),

            'issn_elektronik' => fake()
                ->optional(0.9)
                ->numerify('####-####'),

            'website' => fake()->optional()->url(),

            'aktif' => true,
        ];
    }
}
<?php

namespace Database\Factories;

use App\Models\KekayaanIntelektual;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KekayaanIntelektual>
 */
class KekayaanIntelektualFactory extends Factory
{
    public function definition(): array
    {
        $tahun = fake()->numberBetween(2020, 2025);

        $sudahDicatat = fake()->boolean(60);

        return [
            'judul' => fake()->sentence(
                fake()->numberBetween(5, 12)
            ),

            'jenis' => fake()->randomElement([
                'Hak Cipta',
                'Paten',
                'Paten Sederhana',
                'Merek',
            ]),

            'tahun' => $tahun,

            'tanggal_permohonan' => fake()->dateTimeBetween(
                "{$tahun}-01-01",
                "{$tahun}-06-30"
            ),

            'nomor_permohonan' => fake()->numerify(
                'EC-########'
            ),

            'tanggal_pencatatan' => $sudahDicatat
                ? fake()->dateTimeBetween(
                    "{$tahun}-07-01",
                    "{$tahun}-12-31"
                )
                : null,

            'nomor_pencatatan' => $sudahDicatat
                ? fake()->numerify('HC-########')
                : null,

            'periode' => "{$tahun}",

            'tautan_eksternal' => fake()->optional()->url(),

            'keterangan' => fake()->optional()->sentence(),

            'status' => $sudahDicatat
                ? 'dipublikasikan'
                : 'draft',
        ];
    }
}
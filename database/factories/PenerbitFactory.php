<?php

namespace Database\Factories;

use App\Models\Penerbit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Penerbit>
 */
class PenerbitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => fake()->company(),

            'kode' => fake()
                ->unique()
                ->lexify('PUB-???'),

            'website' => fake()->optional()->url(),

            'email' => fake()->optional()->safeEmail(),

            'telepon' => fake()->optional()->phoneNumber(),

            'alamat' => fake()->optional()->address(),

            'kota' => fake()->optional()->city(),

            'negara' => 'Indonesia',

            'aktif' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Dosen;
use App\Models\ProgramStudi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dosen>
 */
class DosenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'program_studi_id' => ProgramStudi::factory(),

            'nidn' => fake()->unique()->numerify('##########'),

            'nama' => fake()->name(),

            'gelar' => fake()->randomElement([
                'S.Kom., M.Kom.',
                'S.T., M.T.',
                'S.E., M.M.',
                'S.H., M.H.',
                'S.Si., M.Si.',
            ]),

            'email' => fake()->unique()->safeEmail(),

            'scopus_id' => fake()->optional(0.35)->numerify('##########'),

            'orcid_id' => fake()->optional(0.35)->uuid(),

            'google_scholar_id' => fake()->optional(0.35)->lexify('GS??????'),

            'aktif' => true,
        ];
    }
}

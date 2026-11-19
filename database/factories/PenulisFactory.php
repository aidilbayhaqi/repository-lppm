<?php

namespace Database\Factories;

use App\Models\Penulis;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Penulis>
 */
class PenulisFactory extends Factory
{
    public function definition(): array
    {
        $nama = fake()->name();

        return [
            'dosen_id' => null,

            'nama' => $nama,

            'nama_normalisasi' => Str::of($nama)
                ->ascii()
                ->lower()
                ->squish()
                ->toString(),

            'jenis_penulis' => 'eksternal',

            'identitas' => fake()->optional()->bothify('EXT-######'),

            'orcid_id' => fake()->optional(0.25)->uuid(),

            'scopus_id' => fake()->optional(0.25)->numerify('##########'),

            'google_scholar_id' => fake()->optional(0.25)->lexify('GS??????'),

            'aktif' => true,
        ];
    }

    public function dosen(int $dosenId): static
    {
        return $this->state([
            'dosen_id' => $dosenId,
            'jenis_penulis' => 'dosen',
        ]);
    }

    public function mahasiswa(): static
    {
        return $this->state([
            'dosen_id' => null,
            'jenis_penulis' => 'mahasiswa',
        ]);
    }

    public function eksternal(): static
    {
        return $this->state([
            'dosen_id' => null,
            'jenis_penulis' => 'eksternal',
        ]);
    }
}
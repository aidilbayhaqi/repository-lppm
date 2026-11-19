<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->info('Data demo tidak dijalankan di lingkungan ini.');

            return;
        }
        $this->call(DemoSeeder::class);
    }
}

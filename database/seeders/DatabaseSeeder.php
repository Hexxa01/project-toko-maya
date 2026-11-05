<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Tidak ada data demo yang diimpor otomatis. Gunakan shop:create-owner atau DemoShopSeeder secara eksplisit.');
    }
}

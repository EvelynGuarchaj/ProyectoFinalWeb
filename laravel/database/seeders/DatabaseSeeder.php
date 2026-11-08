<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Primero la semilla de verificación y luego la del sector.
     */
    public function run(): void
    {
        $this->call([VerificacionSeeder::class, SectorSeeder::class]);
    }
}

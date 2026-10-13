<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Solo catálogos fijos. NUNCA se crean usuarios de prueba aquí (evita cuentas con claves conocidas).
        $this->call(CatalogosSeeder::class);
    }
}

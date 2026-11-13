<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeders de referência, seguros para produção: não criam pessoas, clientes
 * nem números fictícios. O primeiro administrador é criado com
 * `php artisan app:create-admin`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            CitySeeder::class,
            SystemSettingsSeeder::class,
            CatalogSeeder::class,
        ]);
    }
}

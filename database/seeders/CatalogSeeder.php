<?php

namespace Database\Seeders;

use App\Enums\CatalogType;
use Illuminate\Database\Seeder;

/**
 * Cria os itens padrão de cada catálogo só quando o catálogo está vazio.
 * Depois disso, vale o que o administrador configurar.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CatalogType::cases() as $type) {
            $model = $type->model();

            if ($model::query()->exists()) {
                continue;
            }

            foreach ($type->defaults() as $i => $attributes) {
                $model::query()->create($attributes + ['sort_order' => ($i + 1) * 10]);
            }
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Carrega os municípios do IBGE (código, nome, UF e coordenadas da sede) a partir
 * de database/data/municipios.csv. Pode ser executado de novo: atualiza nome e
 * coordenadas, mas preserva região e marcação de "cidade atendida".
 */
class CitySeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/municipios.csv');
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Arquivo de municípios não encontrado: {$path}");
        }

        fgetcsv($handle, escape: '\\'); // cabeçalho
        $now = now();
        $batch = [];

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            [$code, $name, $uf, $lat, $lng] = $row;
            $batch[] = [
                'ibge_code' => (int) $code,
                'name' => $name,
                'uf' => $uf,
                'latitude' => $lat !== '' ? $lat : null,
                'longitude' => $lng !== '' ? $lng : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) === 500) {
                $this->flush($batch);
                $batch = [];
            }
        }

        $this->flush($batch);
        fclose($handle);
    }

    /** @param list<array<string, mixed>> $batch */
    private function flush(array $batch): void
    {
        if ($batch === []) {
            return;
        }

        DB::table('cities')->upsert($batch, ['ibge_code'], ['name', 'uf', 'latitude', 'longitude', 'updated_at']);
    }
}

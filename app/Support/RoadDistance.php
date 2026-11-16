<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Distância pelas estradas entre pontos em sequência, pelo OpenRouteService
 * (mapa do OpenStreetMap). Só coordenadas são enviadas: nenhum nome ou dado
 * do cliente sai do sistema.
 *
 * - Cada trecho calculado fica guardado em cache por 180 dias: o mesmo par
 *   de pontos não é consultado de novo.
 * - Sem chave configurada, com o serviço fora do ar ou com o limite diário
 *   atingido, usa a estimativa (linha reta × fator de estrada) e deixa de
 *   consultar o serviço por 10 minutos, para não atrasar o uso do sistema.
 */
final class RoadDistance
{
    public const ROAD = 'road';

    public const ESTIMATE = 'estimate';

    private const CACHE_DAYS = 180;

    private const PAUSE_KEY = 'road-distance:paused';

    private const MAX_POINTS = 50;

    /** Consultas feitas ao serviço nesta execução (usado para respeitar o limite por minuto). */
    public int $requests = 0;

    public static function enabled(): bool
    {
        return filled(config('services.openrouteservice.key'));
    }

    public static function paused(): bool
    {
        return Cache::has(self::PAUSE_KEY);
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points  [latitude, longitude] na ordem do trajeto
     * @return array{legs: list<float>, source: string|null} legs[i] = km do ponto i ao i+1; source null quando não há trecho
     */
    public function legs(array $points): array
    {
        $count = count($points);
        if ($count < 2) {
            return ['legs' => [], 'source' => null];
        }

        $keys = [];
        $cached = [];
        for ($i = 0; $i < $count - 1; $i++) {
            $keys[$i] = $this->key($points[$i], $points[$i + 1]);
            $value = Cache::get($keys[$i]);
            if ($value !== null) {
                $cached[$i] = (float) $value;
            }
        }

        if (count($cached) === $count - 1) {
            return ['legs' => array_values($cached), 'source' => self::ROAD];
        }

        $fetched = $this->fetch($points);
        if ($fetched !== null) {
            foreach ($fetched as $i => $km) {
                Cache::put($keys[$i], $km, now()->addDays(self::CACHE_DAYS));
            }

            return ['legs' => $fetched, 'source' => self::ROAD];
        }

        $factor = Geo::roadFactor();
        $legs = [];
        for ($i = 0; $i < $count - 1; $i++) {
            $legs[] = $cached[$i] ?? Geo::roadKm($points[$i][0], $points[$i][1], $points[$i + 1][0], $points[$i + 1][1], $factor);
        }

        return ['legs' => $legs, 'source' => self::ESTIMATE];
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     * @return list<float>|null
     */
    private function fetch(array $points): ?array
    {
        if (! self::enabled() || self::paused() || count($points) > self::MAX_POINTS) {
            return null;
        }

        try {
            $this->requests++;
            $response = Http::baseUrl((string) config('services.openrouteservice.url'))
                ->withHeaders(['Authorization' => (string) config('services.openrouteservice.key')])
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout((int) config('services.openrouteservice.timeout', 6))
                ->post('/v2/directions/driving-car', [
                    'coordinates' => array_map(fn ($p) => [$p[1], $p[0]], $points),
                    // Pontos na zona rural podem ficar longe da estrada mais próxima: sem limite de encaixe.
                    'radiuses' => array_fill(0, count($points), -1),
                    'instructions' => false,
                    'geometry' => false,
                ]);

            $segments = $response->json('routes.0.segments');
            if (! $response->successful() || ! is_array($segments) || count($segments) !== count($points) - 1) {
                return $this->fail('resposta '.$response->status().': '.mb_substr($response->body(), 0, 300));
            }

            return array_map(fn ($s) => round(((float) ($s['distance'] ?? 0)) / 1000, 1), array_values($segments));
        } catch (Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    private function fail(string $reason): null
    {
        Cache::put(self::PAUSE_KEY, true, now()->addMinutes(10));
        Log::warning('Cálculo de rota pela estrada indisponível; usando o fator de estrada por 10 minutos. '.$reason);

        return null;
    }

    /**
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     */
    private function key(array $a, array $b): string
    {
        // 5 casas decimais ≈ 1 metro.
        return 'road-km:'.md5(sprintf('%.5f,%.5f;%.5f,%.5f', $a[0], $a[1], $b[0], $b[1]));
    }
}

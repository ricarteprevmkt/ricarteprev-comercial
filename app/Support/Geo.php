<?php

namespace App\Support;

use App\Models\SystemSetting;

/**
 * Distâncias sem API externa: linha reta (haversine) multiplicada pelo
 * fator de estrada configurado em Parâmetros (padrão 1,30).
 */
final class Geo
{
    private const EARTH_RADIUS_KM = 6371.0;

    public static function straightKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public static function roadFactor(): float
    {
        return (float) SystemSetting::get('routes.road_factor', '1.30');
    }

    /** Distância estimada por estrada, em km com uma casa decimal. */
    public static function roadKm(float $lat1, float $lng1, float $lat2, float $lng2, ?float $factor = null): float
    {
        return round(self::straightKm($lat1, $lng1, $lat2, $lng2) * ($factor ?? self::roadFactor()), 1);
    }

    /** Custo estimado do deslocamento, ou null se o custo por km não foi configurado. */
    public static function estimatedCost(float $km): ?string
    {
        $perKm = SystemSetting::get('routes.cost_per_km');

        return $perKm === null ? null : number_format($km * (float) $perKm, 2, '.', '');
    }
}

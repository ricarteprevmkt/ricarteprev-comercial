<?php

namespace App\Http\Controllers;

use App\Models\C2Snapshot;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recebe os totais do escritório enviados pela tarefa agendada que lê o C2.
 * Autenticação por chave fixa (C2_SNAPSHOT_TOKEN no .env). Só aceita os
 * indicadores conhecidos, todos numéricos: nada de texto, nomes ou CPF.
 */
class C2SnapshotController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = (string) config('services.c2.snapshot_token');
        if ($token === '') {
            return response()->json(['message' => 'Recebimento desligado: C2_SNAPSHOT_TOKEN não está no .env.'], 503);
        }
        if (! hash_equals($token, (string) $request->bearerToken())) {
            return response()->json(['message' => 'Chave inválida.'], 401);
        }

        $keys = C2Snapshot::keys();
        $data = $request->validate([
            'collected_at' => ['required', 'date', 'before_or_equal:+5 minutes'],
            'metrics' => ['required', 'array', 'min:1'],
            'metrics.*' => ['nullable', 'numeric', 'between:-1000000000000,1000000000000'],
        ]);

        $unknown = array_diff(array_keys($data['metrics']), $keys);
        if ($unknown !== []) {
            return response()->json(['message' => 'Indicadores desconhecidos.', 'unknown' => array_values($unknown), 'accepted' => $keys], 422);
        }
        $snapshot = C2Snapshot::query()->create([
            'metrics' => array_map(fn ($v) => $v === null ? null : round((float) $v, 2), $data['metrics']),
            // A tarefa manda em UTC; o banco guarda no fuso do sistema.
            'collected_at' => CarbonImmutable::parse($data['collected_at'])->setTimezone(config('app.timezone')),
        ]);

        C2Snapshot::query()->where('collected_at', '<', now()->subDays(C2Snapshot::KEEP_DAYS))->delete();

        return response()->json(['ok' => true, 'id' => $snapshot->id, 'metrics' => count($data['metrics'])], 201);
    }
}

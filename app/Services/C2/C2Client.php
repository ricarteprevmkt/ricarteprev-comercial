<?php

namespace App\Services\C2;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente da API de Monitoramento do C2 Prev (somente leitura).
 *
 * A chave fica só no .env (C2_API_KEY). Respeita o limite da API: conta as
 * requisições da execução e para ao receber 429/503, deixando o restante para
 * a próxima rodada do agendador.
 */
final class C2Client
{
    public int $requests = 0;

    public static function enabled(): bool
    {
        return filled(config('services.c2.key'));
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @return array<string, mixed>
     *
     * @throws C2Exception
     */
    public function get(string $path, array $query = []): array
    {
        $this->requests++;

        try {
            $response = Http::baseUrl((string) config('services.c2.url'))
                ->withToken((string) config('services.c2.key'))
                ->acceptJson()
                ->timeout((int) config('services.c2.timeout', 15))
                ->get($path, array_filter($query, fn ($v) => $v !== null && $v !== ''));
        } catch (ConnectionException $e) {
            throw new C2Exception('Sem conexão com o C2: '.$e->getMessage(), 0, 'CONNECTION');
        }

        if ($response->successful()) {
            return (array) $response->json();
        }

        throw $this->error($response);
    }

    private function error(Response $response): C2Exception
    {
        $code = (string) ($response->json('code') ?? 'HTTP_'.$response->status());
        $message = match ($response->status()) {
            401 => 'Chave do C2 ausente ou inválida.',
            403 => 'A integração não tem acesso a este recurso no C2.',
            404 => 'Registro não encontrado no C2.',
            410 => 'Posição do feed expirada.',
            429 => 'Limite de consultas ao C2 atingido.',
            503 => 'C2 temporariamente indisponível.',
            default => "Erro {$response->status()} na API do C2.",
        };

        return new C2Exception($message, $response->status(), $code);
    }
}

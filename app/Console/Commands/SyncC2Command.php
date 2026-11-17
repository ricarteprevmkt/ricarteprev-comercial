<?php

namespace App\Console\Commands;

use App\Actions\C2\SyncC2Processes;
use App\Services\C2\C2Client;
use App\Services\C2\C2Exception;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:c2-sync {--check : Só testa a chave e mostra as permissões da integração}')]
#[Description('Traz do C2 Prev o resultado dos processos no INSS e liga cada um ao contato pelo CPF')]
class SyncC2Command extends Command
{
    public function handle(C2Client $client): int
    {
        if (! C2Client::enabled()) {
            $this->line('Integração com o C2 desligada: C2_API_KEY não está no .env.');

            return self::SUCCESS;
        }

        if ($this->option('check')) {
            return $this->check($client);
        }

        $result = (new SyncC2Processes($client))->run();

        if ($result['error'] !== null) {
            $this->error($result['error']);

            return self::FAILURE;
        }

        $this->info("{$result['synced']} processo(s) atualizado(s), {$result['linked']} ligado(s) a contatos, {$result['requests']} consulta(s).");
        if (! $result['backfill_done']) {
            $this->line('Carga inicial em andamento: continua na próxima rodada.');
        }

        return self::SUCCESS;
    }

    private function check(C2Client $client): int
    {
        try {
            $me = $client->get('/v1/me');
        } catch (C2Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Conexão com o C2 funcionando.');
        $this->line('Fontes: '.implode(', ', (array) ($me['sources'] ?? [])));
        $this->line('Permissões: '.implode(', ', (array) ($me['permissions'] ?? [])));

        $missing = array_diff(['processes:read', 'monitoring:read', 'events:read'], (array) ($me['permissions'] ?? []));
        if ($missing !== []) {
            $this->warn('Faltam permissões na chave: '.implode(', ', $missing).'. Ajuste a chave no C2.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

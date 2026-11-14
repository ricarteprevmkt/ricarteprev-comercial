<?php

namespace App\Console\Commands;

use App\Actions\Notifications\SendAlerts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-alerts {--upcoming : Só os avisos de visitas da próxima hora}')]
#[Description('Gera os avisos internos (sino) de pendências e de visitas próximas')]
class SendAlertsCommand extends Command
{
    public function handle(SendAlerts $alerts): int
    {
        $count = $this->option('upcoming') ? $alerts->upcomingVisits() : $alerts->daily();
        $this->info("{$count} aviso(s) enviado(s).");

        return self::SUCCESS;
    }
}

<?php

use Illuminate\Support\Facades\Schedule;

/*
| Tarefas agendadas.
|
| Na Hostinger não há processo permanente (Supervisor), então um único cron
| roda a cada minuto e dispara tudo daqui:
|
|   * * * * * cd ~/ricarteprev && php artisan schedule:run >> /dev/null 2>&1
*/

// Processa a fila (exportações, notificações) e encerra antes do próximo minuto.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(5);

// Auditoria guardada por 2 anos (veja config/activitylog.php).
Schedule::command('activitylog:clean --force')->monthly();

Schedule::command('queue:prune-failed --hours=720')->weekly();

// Avisos internos (sino): visitas da próxima hora e resumo diário de pendências.
Schedule::command('app:send-alerts --upcoming')->everyFifteenMinutes()->withoutOverlapping(10);
Schedule::command('app:send-alerts')->dailyAt('07:30');

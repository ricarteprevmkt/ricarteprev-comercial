<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
| Tarefas agendadas.
|
| Na Hostinger não há processo permanente (Supervisor), então um único cron
| roda a cada minuto e dispara tudo daqui:
|
|   * * * * * cd ~/domains/ricarteprev.com/ricarteprev && php artisan schedule:run >> /dev/null 2>&1
|
| O PHP da Hostinger não permite abrir outros processos (proc_open), então
| cada tarefa roda dentro do próprio schedule:run, pelo Artisan::call, em vez
| do Schedule::command (que abriria um novo processo para cada uma).
*/

$task = fn (string $command) => Schedule::call(fn () => Artisan::call($command))->name($command);

// Processa a fila (exportações, notificações) e encerra antes do próximo minuto.
$task('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(5);

// Auditoria guardada por 2 anos (veja config/activitylog.php).
$task('activitylog:clean --force')->monthly();

$task('queue:prune-failed --hours=720')->weekly();

// Avisos internos (sino): visitas da próxima hora e resumo diário de pendências.
$task('app:send-alerts --upcoming')->everyFifteenMinutes()->withoutOverlapping(10);
$task('app:send-alerts')->dailyAt('07:30');

// Cópia de segurança diária do banco (storage/app/private/backups), guardada por BACKUP_KEEP_DAYS dias.
$task('app:backup-database')->dailyAt('02:30')->withoutOverlapping(60);

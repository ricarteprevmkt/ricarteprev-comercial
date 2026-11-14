<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Cópia do banco MySQL compactada (.sql.gz) em storage/app/private/backups.
 *
 * Usa o mysqldump quando o servidor tem; senão, gera o SQL pelo próprio PHP.
 * A senha nunca vai para a linha de comando: o mysqldump lê de um arquivo
 * temporário com permissão restrita, apagado ao final.
 */
#[Signature('app:backup-database {--keep= : Dias de cópias mantidas (padrão: BACKUP_KEEP_DAYS ou 14)} {--php : Força a cópia pelo PHP, sem mysqldump}')]
#[Description('Gera a cópia de segurança do banco e apaga as cópias antigas')]
class BackupDatabaseCommand extends Command
{
    private const DIR = 'backups';

    public function handle(): int
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? null) !== 'mysql' && ($config['driver'] ?? null) !== 'mariadb') {
            $this->error('O backup automático suporta apenas MySQL/MariaDB.');

            return self::FAILURE;
        }

        $disk = Storage::disk('local');
        $disk->makeDirectory(self::DIR);
        $file = self::DIR.'/'.$config['database'].'-'.now()->format('Ymd-His').'.sql.gz';
        $path = $disk->path($file);

        $dumper = $this->option('php') ? null : (new ExecutableFinder)->find('mysqldump');
        $ok = $dumper ? $this->withMysqldump($dumper, $config, $path) : $this->withPhp($path);

        if (! $ok || ! is_file($path) || filesize($path) < 100) {
            @unlink($path);
            $this->error('Falha ao gerar a cópia do banco.');

            return self::FAILURE;
        }

        $this->info('Cópia gerada: '.$file.' ('.number_format(filesize($path) / 1024, 0, ',', '.').' KB, via '.($dumper ? 'mysqldump' : 'PHP').')');
        $this->prune((int) ($this->option('keep') ?: config('app.backup_keep_days', 14)));

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $config */
    private function withMysqldump(string $binary, array $config, string $path): bool
    {
        $credentials = tempnam(sys_get_temp_dir(), 'mydump');
        file_put_contents($credentials, "[client]\nuser=\"{$config['username']}\"\npassword=\"{$config['password']}\"\nhost=\"{$config['host']}\"\nport={$config['port']}\n");
        @chmod($credentials, 0600);

        try {
            $process = new Process([
                $binary, "--defaults-extra-file={$credentials}", '--single-transaction', '--quick', '--routines',
                '--no-tablespaces', '--default-character-set=utf8mb4', (string) $config['database'],
            ]);
            $process->setTimeout(600);

            $gz = $this->open($path);
            $process->run(function ($type, $buffer) use ($gz) {
                if ($type === Process::OUT) {
                    gzwrite($gz, $buffer);
                }
            });
            gzclose($gz);

            if (! $process->isSuccessful()) {
                $this->warn('mysqldump falhou, usando a cópia pelo PHP: '.trim($process->getErrorOutput()));

                return $this->withPhp($path);
            }

            return true;
        } finally {
            @unlink($credentials);
        }
    }

    private function withPhp(string $path): bool
    {
        $gz = $this->open($path);
        $pdo = DB::connection()->getPdo();

        gzwrite($gz, '-- Cópia gerada em '.now()->format('d/m/Y H:i:s')."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach (DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"') as $row) {
            $table = (string) array_values((array) $row)[0];
            $create = (array) DB::selectOne("SHOW CREATE TABLE `{$table}`");
            gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n".array_values($create)[1].";\n\n");

            $offset = 0;
            while (true) {
                $rows = DB::table($table)->offset($offset)->limit(500)->get();
                if ($rows->isEmpty()) {
                    break;
                }

                $values = $rows->map(function ($r) use ($pdo) {
                    $cols = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), (array) $r);

                    return '('.implode(',', $cols).')';
                })->implode(",\n");

                $columns = '`'.implode('`,`', array_keys((array) $rows->first())).'`';
                gzwrite($gz, "INSERT INTO `{$table}` ({$columns}) VALUES\n{$values};\n");
                $offset += 500;
            }
            gzwrite($gz, "\n");
        }

        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");

        return gzclose($gz);
    }

    /** @return resource */
    private function open(string $path)
    {
        $gz = gzopen($path, 'wb6');

        if ($gz === false) {
            throw new \RuntimeException("Não foi possível criar o arquivo de backup: {$path}");
        }

        return $gz;
    }

    private function prune(int $days): void
    {
        $disk = Storage::disk('local');
        $limit = now()->subDays(max(1, $days))->getTimestamp();
        $removed = 0;

        foreach ($disk->files(self::DIR) as $file) {
            if (str_ends_with($file, '.sql.gz') && $disk->lastModified($file) < $limit) {
                $disk->delete($file);
                $removed++;
            }
        }

        if ($removed > 0) {
            $this->line("{$removed} cópia(s) com mais de {$days} dias apagada(s).");
        }
    }
}

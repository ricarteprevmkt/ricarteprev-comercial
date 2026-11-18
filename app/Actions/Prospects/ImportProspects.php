<?php

namespace App\Actions\Prospects;

use App\Enums\ProspectStatus;
use App\Models\Lead;
use App\Models\Prospect;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

/**
 * Importa a planilha de leads exportada do CRM do C2 (Excel ou CSV).
 *
 * - Identifica a pessoa pelo telefone (DDD + 8 últimos dígitos): quem já foi
 *   importado é só atualizado; situação e executivo não mudam.
 * - Marca o interessado cujo telefone já é de um contato do comercial.
 * - Traz só o necessário: nome, telefone, origem, demanda, observações, data,
 *   responsável e etapa no C2.
 */
final class ImportProspects
{
    /** Cabeçalho da planilha (sem acento, minúsculo) => campo. */
    private const COLUMNS = [
        'nome' => 'name',
        'telefone' => 'phone',
        'origem' => 'origin',
        'demanda' => 'demand',
        'observacoes' => 'notes',
        'responsavel' => 'c2_owner',
        'etapa' => 'c2_stage',
        'data de criacao' => 'entered_at',
    ];

    /** @return array{created: int, updated: int, invalid: int, contacts: int} */
    public function handle(string $path, string $extension): array
    {
        $rows = $this->read($path, strtolower($extension));
        $header = array_map(fn ($h) => Str::of((string) $h)->ascii()->lower()->trim()->value(), array_shift($rows) ?? []);
        $index = [];
        foreach (self::COLUMNS as $title => $field) {
            $pos = array_search($title, $header, true);
            if ($pos !== false) {
                $index[$field] = $pos;
            }
        }

        if (! isset($index['name'], $index['phone'])) {
            throw ValidationException::withMessages(['file' => 'A planilha precisa das colunas "Nome" e "Telefone" (exportação de leads do C2).']);
        }

        $leadKeys = $this->leadKeys();
        $result = ['created' => 0, 'updated' => 0, 'invalid' => 0, 'contacts' => 0];
        $now = now();

        foreach ($rows as $row) {
            $get = fn (string $field) => isset($index[$field]) ? $this->clean($row[$index[$field]] ?? null) : null;
            $key = Prospect::phoneKey($get('phone'));
            $name = $get('name');

            if ($key === null) {
                $result['invalid']++;

                continue;
            }

            $data = array_filter([
                'origin' => $get('origin'),
                'demand' => $get('demand'),
                'notes' => $get('notes'),
                'c2_owner' => $get('c2_owner'),
                'c2_stage' => $get('c2_stage'),
            ], fn ($v) => $v !== null);

            $prospect = Prospect::query()->firstWhere('phone_key', $key);

            if ($prospect === null) {
                $prospect = new Prospect([
                    'name' => Str::limit($name ?? 'Sem nome', 250, ''),
                    'phone' => (string) Phone::normalize($get('phone')),
                    'phone_key' => $key,
                    'entered_at' => $this->date($index['entered_at'] ?? null, $row) ?? $now->toDateString(),
                    'status' => ProspectStatus::New,
                ]);
                $result['created']++;
            } else {
                $result['updated']++;
            }

            $prospect->fill($data + ['last_imported_at' => $now]);
            if ($prospect->lead_id === null && $prospect->status === ProspectStatus::New && isset($leadKeys[$key])) {
                $prospect->lead_id = $leadKeys[$key];
            }
            $prospect->save();
        }

        $result['contacts'] = Prospect::query()->where('status', ProspectStatus::New->value)->whereNotNull('lead_id')->count();

        return $result;
    }

    /** @return list<array<int, mixed>> */
    private function read(string $path, string $extension): array
    {
        $reader = $this->reader($path, $extension);

        try {
            $reader->open($path);
            $rows = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }
                break; // só a primeira aba
            }
            $reader->close();
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => 'Não foi possível ler a planilha. Envie o arquivo .xlsx ou .csv exportado do C2.']);
        }

        return $rows;
    }

    private function reader(string $path, string $extension): CsvReader|XlsxReader
    {
        if ($extension === 'csv' || $extension === 'txt') {
            // Excel em português grava CSV com ";"; outros sistemas, com ",".
            $first = (string) fgets(fopen($path, 'r') ?: throw ValidationException::withMessages(['file' => 'Não foi possível abrir o arquivo.']));

            return new CsvReader(new CsvOptions(FIELD_DELIMITER: substr_count($first, ';') > substr_count($first, ',') ? ';' : ','));
        }

        return new XlsxReader;
    }

    /** @return array<string, int> chave do telefone => id do contato */
    private function leadKeys(): array
    {
        $keys = [];
        Lead::query()->select(['id', 'phone', 'whatsapp'])->orderBy('id')->each(function (Lead $lead) use (&$keys) {
            foreach ([$lead->phone, $lead->whatsapp] as $phone) {
                if ($key = Prospect::phoneKey($phone)) {
                    $keys[$key] = $lead->id;
                }
            }
        });

        return $keys;
    }

    /** "-" e vazio viram null. */
    private function clean(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        $text = trim((string) $value);

        return $text === '' || $text === '-' ? null : $text;
    }

    /** @param  array<int, mixed>  $row */
    private function date(?int $pos, array $row): ?string
    {
        $value = $pos === null ? null : ($row[$pos] ?? null);

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->toDateString();
        }

        $text = trim((string) $value);
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})#', $text, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            $date = "{$m[3]}-{$m[2]}-{$m[1]}";

            return $date > today()->toDateString() ? today()->toDateString() : $date;
        }

        return null;
    }
}

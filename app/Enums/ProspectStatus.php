<?php

namespace App\Enums;

enum ProspectStatus: string
{
    case New = 'new';
    case Qualified = 'qualified';
    case Discarded = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Novo',
            self::Qualified => 'Qualificado',
            self::Discarded => 'Descartado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'sky',
            self::Qualified => 'green',
            self::Discarded => 'zinc',
        };
    }

    /** @return array<string, string> motivo => rótulo */
    public static function discardReasons(): array
    {
        return [
            'sem_interesse' => 'Sem interesse',
            'sem_perfil' => 'Sem perfil para o serviço',
            'sem_resposta' => 'Não respondeu',
            'contato_invalido' => 'Contato inválido',
            'duplicado' => 'Duplicado',
            'outro' => 'Outro motivo',
        ];
    }
}

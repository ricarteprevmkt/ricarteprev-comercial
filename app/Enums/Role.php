<?php

namespace App\Enums;

/**
 * Perfis de acesso. A ordem importa: é usada para definir o perfil principal de um usuário.
 */
enum Role: string
{
    case Admin = 'administrador';
    case Manager = 'gestor';
    case Executive = 'executivo';
    case Indicator = 'indicador';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Manager => 'Gestor comercial',
            self::Executive => 'Executivo comercial',
            self::Indicator => 'Indicador regional',
        };
    }
}

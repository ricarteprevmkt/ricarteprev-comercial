<?php

namespace App\Enums;

/**
 * Perfis de acesso. A ordem importa: é usada para definir o perfil principal de um usuário.
 */
enum Role: string
{
    case Admin = 'administrador';
    case Manager = 'gestor';
    case Administrative = 'administrativo';
    case Executive = 'executivo';
    case Indicator = 'indicador';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Manager => 'Gestor comercial',
            self::Administrative => 'Administrativo',
            self::Executive => 'Executivo comercial',
            self::Indicator => 'Parceiro regional',
        };
    }
}

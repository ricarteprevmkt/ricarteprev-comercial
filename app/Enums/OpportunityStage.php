<?php

namespace App\Enums;

/**
 * Etapas da oportunidade. As cinco primeiras formam o funil (rank 1 a 5);
 * as demais são situações fora do funil.
 */
enum OpportunityStage: string
{
    case Contact = 'contact';
    case Qualified = 'qualified';
    case Scheduled = 'scheduled';
    case Proposal = 'proposal';
    case Signed = 'signed';
    case Lost = 'lost';
    case Disqualified = 'disqualified';
    case FollowUp = 'follow_up';

    public function label(): string
    {
        return match ($this) {
            self::Contact => 'Contato',
            self::Qualified => 'Qualificado',
            self::Scheduled => 'Atendimento agendado',
            self::Proposal => 'Proposta apresentada',
            self::Signed => 'Contrato assinado',
            self::Lost => 'Perdido',
            self::Disqualified => 'Desqualificado',
            self::FollowUp => 'Em acompanhamento',
        };
    }

    /** Posição no funil, ou null para situações fora do funil. */
    public function rank(): ?int
    {
        return match ($this) {
            self::Contact => 1,
            self::Qualified => 2,
            self::Scheduled => 3,
            self::Proposal => 4,
            self::Signed => 5,
            default => null,
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Signed, self::Lost, self::Disqualified], true);
    }

    public function requiresReason(): bool
    {
        return in_array($this, [self::Lost, self::Disqualified], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::Contact => 'zinc',
            self::Qualified => 'sky',
            self::Scheduled => 'blue',
            self::Proposal => 'amber',
            self::Signed => 'green',
            self::Lost, self::Disqualified => 'red',
            self::FollowUp => 'violet',
        };
    }

    /** @return list<self> etapas do funil, em ordem */
    public static function funnel(): array
    {
        return [self::Contact, self::Qualified, self::Scheduled, self::Proposal, self::Signed];
    }

    /**
     * Etapas que o usuário pode escolher ao mover a oportunidade. "Contrato
     * assinado" só é alcançado registrando o contrato.
     *
     * @return list<self>
     */
    public static function movable(): array
    {
        return [self::Contact, self::Qualified, self::Scheduled, self::Proposal, self::FollowUp, self::Lost, self::Disqualified];
    }

    public static function fromRank(int $rank): self
    {
        return self::funnel()[$rank - 1];
    }
}

<?php

namespace App\Enums;

/**
 * Evolução de uma indicação. As seis primeiras são uma sequência (rank 1 a 6);
 * "Perdida" fica fora da sequência.
 */
enum ReferralStatus: string
{
    case Referred = 'referred';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Opportunity = 'opportunity';
    case Proposal = 'proposal';
    case Signed = 'signed';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Referred => 'Indicada',
            self::Contacted => 'Contato realizado',
            self::Qualified => 'Qualificada',
            self::Opportunity => 'Oportunidade gerada',
            self::Proposal => 'Proposta apresentada',
            self::Signed => 'Contrato assinado',
            self::Lost => 'Perdida',
        };
    }

    public function rank(): ?int
    {
        return match ($this) {
            self::Referred => 1,
            self::Contacted => 2,
            self::Qualified => 3,
            self::Opportunity => 4,
            self::Proposal => 5,
            self::Signed => 6,
            self::Lost => null,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Referred => 'zinc',
            self::Contacted => 'sky',
            self::Qualified => 'blue',
            self::Opportunity => 'indigo',
            self::Proposal => 'amber',
            self::Signed => 'green',
            self::Lost => 'red',
        };
    }

    /** @return list<self> */
    public static function sequence(): array
    {
        return [self::Referred, self::Contacted, self::Qualified, self::Opportunity, self::Proposal, self::Signed];
    }

    public static function fromRank(int $rank): self
    {
        return self::sequence()[max(1, min(6, $rank)) - 1];
    }

    /**
     * Situação correspondente à etapa da oportunidade do contato indicado.
     * "Atendimento agendado" é o ponto em que a indicação vira "Oportunidade gerada".
     */
    public static function fromOpportunityStage(OpportunityStage $stage): ?self
    {
        return match ($stage) {
            OpportunityStage::Contact => self::Contacted,
            OpportunityStage::Qualified => self::Qualified,
            OpportunityStage::Scheduled => self::Opportunity,
            OpportunityStage::Proposal => self::Proposal,
            OpportunityStage::Signed => self::Signed,
            default => null,
        };
    }
}

<?php

namespace App\Actions\Referrals;

use App\Enums\CommissionBase;
use App\Enums\CommissionStatus;
use App\Enums\CommissionTrigger;
use App\Enums\CommissionType;
use App\Models\Commission;
use App\Models\CommissionPayment;
use App\Models\CommissionRule;
use App\Models\Contract;
use App\Models\ContractReceipt;
use App\Models\Referral;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Comissões de indicadores.
 *
 * Geração (automática), conforme a regra associada ao indicador do contrato:
 * - o evento da regra (assinatura ou recebimento) define QUANDO gera;
 * - a base define SOBRE O QUÊ: valor do contrato e receita prevista geram
 *   uma vez por contrato; receita recebida gera a cada recebimento;
 * - valor fixo gera uma vez por contrato.
 * Sem indicador ou sem regra ativa, nada é gerado.
 *
 * Depois: aprovar (valor aprovado), registrar pagamentos e, se for o caso, cancelar.
 */
final class Commissions
{
    public function onContractSigned(Contract $contract): ?Commission
    {
        $rule = $this->ruleFor($contract);

        if ($rule === null || $rule->trigger_event !== CommissionTrigger::ContractSigned || $rule->base === CommissionBase::ReceivedRevenue) {
            return null;
        }

        return $this->generateOnce($contract, $rule, null);
    }

    public function onReceipt(ContractReceipt $receipt): ?Commission
    {
        $contract = $receipt->contract;
        $rule = $this->ruleFor($contract);

        if ($rule === null || $rule->trigger_event !== CommissionTrigger::RevenueReceived) {
            return null;
        }

        if ($rule->type === CommissionType::Percent && $rule->base === CommissionBase::ReceivedRevenue) {
            return $this->create($contract, $rule, $receipt, base: (string) $receipt->amount);
        }

        return $this->generateOnce($contract, $rule, $receipt);
    }

    public function approve(Commission $commission, string $amount, User $user, ?string $note = null): Commission
    {
        if ($commission->status !== CommissionStatus::Expected) {
            throw ValidationException::withMessages(['approve.amount' => 'Só comissões previstas podem ser aprovadas.']);
        }

        $commission->forceFill([
            'status' => CommissionStatus::Approved,
            'approved_amount' => $amount,
            'approved_by' => $user->id,
            'approved_at' => now(),
            'notes' => $note ?? $commission->notes,
        ])->save();

        return $commission;
    }

    public function pay(Commission $commission, string $amount, string $paidAt, User $user, ?string $note = null): CommissionPayment
    {
        if ($commission->status !== CommissionStatus::Approved) {
            throw ValidationException::withMessages(['payment.amount' => 'Aprove a comissão antes de registrar o pagamento.']);
        }

        $remaining = Money::sub($commission->approved_amount, $commission->paidTotal());

        if (Money::compare($amount, $remaining) === 1) {
            throw ValidationException::withMessages(['payment.amount' => 'O pagamento passa do valor aprovado. Falta pagar '.Money::format($remaining).'.']);
        }

        return DB::transaction(function () use ($commission, $amount, $paidAt, $user, $note, $remaining) {
            $payment = CommissionPayment::query()->create([
                'commission_id' => $commission->id,
                'paid_at' => $paidAt,
                'amount' => $amount,
                'notes' => $note,
                'created_by' => $user->id,
            ]);

            if (Money::compare($amount, $remaining) === 0) {
                $commission->forceFill(['status' => CommissionStatus::Paid])->save();
            }

            return $payment;
        });
    }

    public function cancel(Commission $commission, string $reason): Commission
    {
        if (! in_array($commission->status, [CommissionStatus::Expected, CommissionStatus::Approved], true) || $commission->payments()->exists()) {
            throw ValidationException::withMessages(['cancel.reason' => 'Só comissões sem pagamento podem ser canceladas.']);
        }

        $commission->forceFill(['status' => CommissionStatus::Cancelled, 'notes' => $reason])->save();

        return $commission;
    }

    private function ruleFor(Contract $contract): ?CommissionRule
    {
        if ($contract->regional_indicator_id === null) {
            return null;
        }

        $rule = $contract->indicator?->commissionRule;

        return $rule && $rule->active ? $rule : null;
    }

    private function generateOnce(Contract $contract, CommissionRule $rule, ?ContractReceipt $receipt): ?Commission
    {
        $exists = Commission::query()
            ->where('contract_id', $contract->id)
            ->where('regional_indicator_id', $contract->regional_indicator_id)
            ->where('status', '!=', CommissionStatus::Cancelled->value)
            ->exists();

        if ($exists) {
            return null;
        }

        $base = match (true) {
            $rule->type === CommissionType::Fixed => null,
            $rule->base === CommissionBase::ExpectedRevenue => (string) ($contract->expected_revenue ?? '0'),
            default => (string) $contract->contract_value,
        };

        return $this->create($contract, $rule, $receipt, $base);
    }

    private function create(Contract $contract, CommissionRule $rule, ?ContractReceipt $receipt, ?string $base): Commission
    {
        $expected = $rule->type === CommissionType::Fixed
            ? (string) $rule->value
            : Money::percent($base, $rule->value);

        $referralId = Referral::query()
            ->where('lead_id', $contract->lead_id)
            ->where('regional_indicator_id', $contract->regional_indicator_id)
            ->where('is_duplicate', false)
            ->latest('referred_at')
            ->value('id');

        return Commission::query()->create([
            'regional_indicator_id' => $contract->regional_indicator_id,
            'referral_id' => $referralId,
            'contract_id' => $contract->id,
            'contract_receipt_id' => $receipt?->id,
            'commission_rule_id' => $rule->id,
            'rule_snapshot' => mb_substr($rule->name.': '.$rule->summary(), 0, 255),
            'base_amount' => $base,
            'expected_amount' => $expected,
            'generated_at' => now()->toDateString(),
        ]);
    }
}

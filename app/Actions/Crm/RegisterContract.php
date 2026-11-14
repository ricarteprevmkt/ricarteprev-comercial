<?php

namespace App\Actions\Crm;

use App\Actions\Referrals\Commissions;
use App\Enums\ContractStatus;
use App\Enums\LeadStatus;
use App\Enums\OpportunityStage;
use App\Models\Contract;
use App\Models\ContractReceipt;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra o contrato de uma oportunidade.
 *
 * - Assinado: a oportunidade vai para "Contrato assinado" e o contato vira cliente.
 * - Aguardando conclusão: o contrato fica pendente e a oportunidade não muda.
 *
 * Origem, executivo e indicador são copiados da oportunidade, para que o
 * resultado fique atribuído a quem gerou o negócio mesmo se o cadastro mudar.
 */
final class RegisterContract
{
    public function __construct(private MoveOpportunity $move, private Commissions $commissions) {}

    /**
     * @param  array{number?: string|null, status: ContractStatus, signed_at?: string|null, contract_value: string, expected_revenue?: string|null, notes?: string|null}  $data
     */
    public function handle(Opportunity $opportunity, array $data, ?User $user): Contract
    {
        if ($opportunity->contract()->exists()) {
            throw ValidationException::withMessages(['contract.number' => 'Esta oportunidade já tem contrato registrado.']);
        }

        if (in_array($opportunity->stage, [OpportunityStage::Lost, OpportunityStage::Disqualified], true)) {
            throw ValidationException::withMessages(['contract.number' => 'Reabra a oportunidade antes de registrar o contrato.']);
        }

        if ($data['status'] === ContractStatus::Signed && empty($data['signed_at'])) {
            throw ValidationException::withMessages(['contract.signed_at' => 'Informe a data de assinatura.']);
        }

        return DB::transaction(function () use ($opportunity, $data, $user) {
            $contract = Contract::query()->create([
                'opportunity_id' => $opportunity->id,
                'lead_id' => $opportunity->lead_id,
                'executive_id' => $opportunity->executive_id,
                'lead_source_id' => $opportunity->lead_source_id,
                'regional_indicator_id' => $opportunity->regional_indicator_id,
                'number' => $data['number'] ?? null,
                'status' => $data['status'],
                'signed_at' => $data['status'] === ContractStatus::Signed ? ($data['signed_at'] ?? null) : null,
                'contract_value' => $data['contract_value'],
                'expected_revenue' => $data['expected_revenue'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
            ]);

            if ($data['status'] === ContractStatus::Signed) {
                $this->markSigned($opportunity, $user);
                $this->commissions->onContractSigned($contract);
            } else {
                $opportunity->forceFill(['last_activity_at' => now()])->save();
            }

            return $contract;
        });
    }

    /** Conclui um contrato que estava aguardando conclusão. */
    public function sign(Contract $contract, string $signedAt, ?User $user): Contract
    {
        if ($contract->status !== ContractStatus::Pending) {
            throw ValidationException::withMessages(['signedAt' => 'Só contratos aguardando conclusão podem ser assinados.']);
        }

        return DB::transaction(function () use ($contract, $signedAt, $user) {
            $contract->update(['status' => ContractStatus::Signed, 'signed_at' => $signedAt]);
            $this->markSigned($contract->opportunity, $user);
            $this->commissions->onContractSigned($contract);

            return $contract;
        });
    }

    /**
     * @param  array{received_at: string, amount: string, notes?: string|null}  $data
     */
    public function registerReceipt(Contract $contract, array $data, ?User $user): ContractReceipt
    {
        if ($contract->status !== ContractStatus::Signed) {
            throw ValidationException::withMessages(['receipt.amount' => 'Registre recebimentos só de contratos assinados.']);
        }

        return DB::transaction(function () use ($contract, $data, $user) {
            $receipt = ContractReceipt::query()->create([
                'contract_id' => $contract->id,
                'received_at' => $data['received_at'],
                'amount' => $data['amount'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
            ]);
            $this->commissions->onReceipt($receipt);

            return $receipt;
        });
    }

    private function markSigned(Opportunity $opportunity, ?User $user): void
    {
        if ($opportunity->stage !== OpportunityStage::Signed) {
            $this->move->handle($opportunity, OpportunityStage::Signed, $user, 'Contrato assinado', internal: true);
        }

        $opportunity->lead()->update(['status' => LeadStatus::Client->value]);
    }
}

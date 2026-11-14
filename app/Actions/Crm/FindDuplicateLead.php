<?php

namespace App\Actions\Crm;

use App\Models\Lead;
use App\Support\Document;
use App\Support\Phone;

/**
 * Procura um contato já cadastrado com o mesmo CPF/CNPJ (duplicidade certa)
 * ou com o mesmo telefone/WhatsApp (duplicidade provável).
 */
final class FindDuplicateLead
{
    /**
     * @return array{lead: Lead, by: 'document'|'phone'}|null
     */
    public function handle(?string $document, ?string $phone, ?string $whatsapp, ?int $ignoreId = null): ?array
    {
        $hash = Document::hash($document);

        if ($hash !== null) {
            $lead = Lead::withTrashed()->where('document_hash', $hash)->whereKeyNot($ignoreId ?? 0)->first();
            if ($lead) {
                return ['lead' => $lead, 'by' => 'document'];
            }
        }

        $numbers = array_values(array_filter(array_unique([Phone::normalize($phone), Phone::normalize($whatsapp)])));

        if ($numbers !== []) {
            $lead = Lead::query()
                ->whereKeyNot($ignoreId ?? 0)
                ->where(fn ($q) => $q->whereIn('phone', $numbers)->orWhereIn('whatsapp', $numbers))
                ->first();
            if ($lead) {
                return ['lead' => $lead, 'by' => 'phone'];
            }
        }

        return null;
    }
}

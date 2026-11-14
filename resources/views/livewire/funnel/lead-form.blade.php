<div>
    <flux:modal name="lead-form" class="w-full max-w-2xl">
        <form wire:submit="save" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ $leadId ? 'Editar contato' : 'Novo contato' }}</flux:heading>
                <flux:text class="mt-1">Obrigatórios: nome, telefone ou WhatsApp, origem e responsável.</flux:text>
            </div>

            <fieldset class="grid gap-4 sm:grid-cols-2">
                <legend class="mb-2 text-sm font-semibold text-brand-900 sm:col-span-2">Contato</legend>
                <flux:radio.group wire:model.live="form.type" variant="segmented" class="sm:col-span-2" aria-label="Tipo de contato">
                    @foreach ($types as $type)
                        <flux:radio :value="$type->value" :label="$type->label()" />
                    @endforeach
                </flux:radio.group>
                <flux:input wire:model="form.name" :label="$form['type'] === 'pj' ? 'Razão social' : 'Nome completo'" required class="sm:col-span-2" />
                <flux:input wire:model="form.phone" type="tel" label="Telefone" placeholder="(88) 99999-0000" />
                <flux:input wire:model="form.whatsapp" type="tel" label="WhatsApp" placeholder="Se for diferente do telefone" />
                @if ($samePhoneWarning)
                    <flux:checkbox wire:model="allowSamePhone" label="É outra pessoa com o mesmo telefone. Cadastrar mesmo assim." class="sm:col-span-2" />
                @endif
                <flux:input wire:model="form.document" :label="($form['type'] === 'pj' ? 'CNPJ' : 'CPF').' (opcional)'" description="Guardado criptografado. Impede cadastro em duplicidade." />
                <flux:input wire:model="form.email" type="email" label="E-mail" />
            </fieldset>

            <fieldset class="grid gap-4 sm:grid-cols-2">
                <legend class="mb-2 text-sm font-semibold text-brand-900 sm:col-span-2">Localização</legend>
                <flux:select wire:model="form.city_id" label="Cidade">
                    <flux:select.option value="">Selecione</flux:select.option>
                    @foreach ($cities as $city)
                        <flux:select.option :value="$city->id">{{ $city->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.neighborhood" label="Bairro" />
                <flux:input wire:model="form.address" label="Endereço" class="sm:col-span-2" />
            </fieldset>

            <fieldset class="grid gap-4 sm:grid-cols-2">
                <legend class="mb-2 text-sm font-semibold text-brand-900 sm:col-span-2">Origem e responsável</legend>
                <flux:select wire:model="form.lead_source_id" label="Origem do contato" required>
                    <flux:select.option value="">Selecione</flux:select.option>
                    @foreach ($sources as $source)
                        <flux:select.option :value="$source->id">{{ $source->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="form.executive_id" label="Executivo responsável" required :disabled="$executives->count() === 1 && ! auth()->user()->can('funnel.view_all')">
                    <flux:select.option value="">Selecione</flux:select.option>
                    @foreach ($executives as $executive)
                        <flux:select.option :value="$executive->id">{{ $executive->user->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="form.regional_indicator_id" label="Indicado por (opcional)">
                    <flux:select.option value="">Ninguém</flux:select.option>
                    @foreach ($indicators as $indicator)
                        <flux:select.option :value="$indicator->id">{{ $indicator->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.entered_at" type="date" label="Data de entrada" required />
                @if ($leadId)
                    <flux:select wire:model="form.status" label="Situação">
                        @foreach ($statuses as $status)
                            <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
                <flux:textarea wire:model="form.notes" label="Observações" rows="2" class="sm:col-span-2"
                    description="Não registre informações médicas nem documentos previdenciários." />
            </fieldset>

            @unless ($leadId)
                <fieldset class="grid gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <flux:switch wire:model.live="openOpportunity" label="Abrir oportunidade agora" />
                    </div>
                    @if ($openOpportunity)
                        <flux:select wire:model="opportunity.stage" label="Etapa inicial">
                            @foreach ($initialStages as $stage)
                                <flux:select.option :value="$stage->value">{{ $stage->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="opportunity.estimated_value" type="number" min="0" step="0.01" label="Valor estimado (R$)" />
                        <flux:input wire:model="opportunity.interest" label="Interesse" placeholder="Ex.: aposentadoria por idade" class="sm:col-span-2" />
                        <flux:input wire:model="opportunity.next_action" label="Próxima ação" placeholder="Ex.: ligar para agendar" />
                        <flux:input wire:model="opportunity.next_action_due" type="date" label="Prazo" />
                    @endif
                </fieldset>
            @endunless

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Salvar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

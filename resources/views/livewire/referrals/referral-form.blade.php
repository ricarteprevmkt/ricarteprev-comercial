<div>
    <flux:modal name="referral-form" class="w-full max-w-lg">
        <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <flux:heading size="lg">Registrar indicação</flux:heading>
                <flux:text class="mt-1">Só o necessário para o primeiro contato. Não registre informações de saúde nem documentos.</flux:text>
            </div>

            @if ($team)
                <flux:select wire:model="form.regional_indicator_id" label="Parceiro" required class="sm:col-span-2">
                    <flux:select.option value="">Selecione</flux:select.option>
                    @foreach ($indicators as $indicator)
                        <flux:select.option :value="$indicator->id">{{ $indicator->name }}{{ $indicator->city ? ' · '.$indicator->city->name : '' }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:input wire:model="form.name" label="Nome de quem foi indicado" required class="sm:col-span-2" />
            <flux:input wire:model="form.phone" type="tel" label="Telefone ou WhatsApp" placeholder="(88) 99999-0000" required />
            <flux:select wire:model="form.city_id" label="Cidade" required>
                <flux:select.option value="">Selecione</flux:select.option>
                @foreach ($cities as $city)
                    <flux:select.option :value="$city->id">{{ $city->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="form.neighborhood" label="Bairro" />

            @if ($team)
                <flux:input wire:model="form.referred_at" type="date" label="Data da indicação" required />
                <flux:select wire:model="form.referral_channel_id" label="Canal">
                    <flux:select.option value="">Do cadastro do parceiro</flux:select.option>
                    @foreach ($channels as $channel)
                        <flux:select.option :value="$channel->id">{{ $channel->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="form.executive_id" label="Executivo responsável">
                    <flux:select.option value="">Do cadastro do parceiro</flux:select.option>
                    @foreach ($executives as $executive)
                        <flux:select.option :value="$executive->id">{{ $executive->user->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:textarea wire:model="form.notes" label="Observações (opcional)" rows="2" class="sm:col-span-2" placeholder="Ex.: melhor horário para ligar" />
            <flux:checkbox wire:model="form.consent" label="A pessoa indicada autorizou ser contatada pela Ricarte Prev." class="sm:col-span-2" />

            <div class="flex justify-end gap-2 sm:col-span-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Registrar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

<div>
    <flux:modal name="expense-form" class="w-full max-w-lg">
        <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <flux:heading size="lg">Lançar despesa</flux:heading>
                <flux:text class="mt-1">Valor efetivamente gasto. Fica aguardando aprovação do gestor.</flux:text>
            </div>
            <flux:select wire:model="form.expense_category_id" label="Categoria" required>
                <flux:select.option value="">Selecione</flux:select.option>
                @foreach ($categories as $category)
                    <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="form.amount" type="number" min="0" step="0.01" inputmode="decimal" label="Valor (R$)" required />
            <flux:input wire:model="form.description" label="Descrição" placeholder="Ex.: abastecimento posto Centro" required class="sm:col-span-2" />
            <flux:input wire:model="form.spent_on" type="date" label="Data" required />
            <flux:select wire:model="form.city_id" label="Cidade">
                <flux:select.option value="">Selecione</flux:select.option>
                @foreach ($cities as $city)
                    <flux:select.option :value="$city->id">{{ $city->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="form.executive_id" label="Executivo" class="sm:col-span-2" :disabled="$executives->count() === 1 && ! auth()->user()->can('finance.view')">
                <flux:select.option value="">Despesa geral (sem executivo)</flux:select.option>
                @foreach ($executives as $executive)
                    <flux:select.option :value="$executive->id">{{ $executive->user->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="sm:col-span-2">
                <flux:input type="file" wire:model="receipt" label="Comprovante (opcional)" accept="image/*,application/pdf" capture="environment" description="Foto ou PDF, até 8 MB." />
                <div wire:loading wire:target="receipt" class="mt-1 text-xs text-zinc-500">Enviando arquivo...</div>
            </div>
            @if ($form['visit_id'])
                <p class="text-xs text-zinc-500 sm:col-span-2">Vinculada à visita #{{ $form['visit_id'] }}.</p>
            @endif
            <div class="flex justify-end gap-2 sm:col-span-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="receipt,save">Lançar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

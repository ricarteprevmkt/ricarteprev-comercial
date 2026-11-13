<x-layouts::app :title="$title">
    <div class="flex flex-col gap-8">
        <x-page-header :title="$title" :subtitle="$subtitle" :period="true" />

        <section class="mx-auto flex max-w-lg flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-300 bg-white px-6 py-14 text-center">
            <span class="flex size-12 items-center justify-center rounded-full bg-gold-100 text-gold-700">
                <flux:icon.wrench-screwdriver class="size-6" />
            </span>
            <h2 class="text-lg font-semibold text-brand-900">Módulo em desenvolvimento</h2>
            <p class="text-sm text-zinc-500">
                Esta área será liberada na <strong class="text-zinc-700">{{ $phase }}</strong>.
                Os números aparecem aqui assim que houver registros cadastrados no sistema.
            </p>
        </section>
    </div>
</x-layouts::app>

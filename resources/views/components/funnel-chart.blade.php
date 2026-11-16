@props([
    // Lista de etapas: ['label' => string, 'count' => int, 'note' => ?string]
    'stages' => [],
    'colors' => ['bg-brand-900', 'bg-brand-700', 'bg-brand-500', 'bg-brand-400', 'bg-success-500'],
])

@php
    // Largura de cada faixa: 30% fixos + 70% proporcionais à maior etapa, para
    // que as etapas pequenas continuem legíveis. Cada faixa é sempre um pouco
    // mais estreita que a anterior e se afunila até a seguinte, formando o
    // desenho do funil; os números exatos ficam escritos em cada faixa.
    $max = max(1, ...array_map(fn ($s) => (int) $s['count'], $stages ?: [['count' => 0]]));
    $widths = [];
    foreach ($stages as $i => $s) {
        $w = 30 + 70 * (int) $s['count'] / $max;
        $widths[] = $i === 0 ? $w : max(16, min($w, $widths[$i - 1] - 6));
    }
    $last = count($widths) - 1;
@endphp

<ol {{ $attributes->class('flex flex-col gap-1') }}>
    @foreach ($stages as $i => $stage)
        @php
            $w = $widths[$i];
            $bottom = $i < $last ? min($w, $widths[$i + 1]) : $w * 0.82;
            $inset = round(($w - $bottom) / 2 / $w * 100, 2);
        @endphp
        <li class="grid grid-cols-[7.5rem_1fr_4rem] items-center gap-3 text-sm sm:grid-cols-[11rem_1fr_5rem]">
            <span class="font-medium leading-tight text-zinc-700">{{ $stage['label'] }}</span>
            <span class="flex justify-center" aria-hidden="true">
                <span class="flex h-11 items-center justify-center {{ $colors[$i % count($colors)] }}"
                    style="width: {{ $w }}%; clip-path: polygon(0 0, 100% 0, {{ 100 - $inset }}% 100%, {{ $inset }}% 100%);">
                    <strong class="tabular-nums text-white">{{ number_format((int) $stage['count'], 0, ',', '.') }}</strong>
                </span>
            </span>
            <span class="text-end tabular-nums">
                <span class="sr-only">{{ number_format((int) $stage['count'], 0, ',', '.') }}</span>
                <span class="text-xs text-zinc-500">{{ $stage['note'] ?? '' }}</span>
            </span>
        </li>
    @endforeach
</ol>

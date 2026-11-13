<?php

use App\Support\Period;
use Carbon\CarbonImmutable;

$today = CarbonImmutable::parse('2026-11-13 15:30:00');

it('cobre dias inteiros nos últimos 30 dias, incluindo hoje', function () use ($today) {
    $p = Period::fromPreset('30d', $today);

    expect($p->start->toDateTimeString())->toBe('2026-10-15 00:00:00')
        ->and($p->end->toDateTimeString())->toBe('2026-11-13 23:59:59')
        ->and($p->days())->toBe(30);
});

it('calcula o período anterior com a mesma duração e sem sobreposição', function () use ($today) {
    $prev = Period::fromPreset('30d', $today)->previous();

    expect($prev->start->toDateString())->toBe('2026-09-15')
        ->and($prev->end->toDateTimeString())->toBe('2026-10-14 23:59:59')
        ->and($prev->days())->toBe(30);
});

it('usa o mês fechado no preset de mês anterior', function () {
    $p = Period::fromPreset('mes_anterior', CarbonImmutable::parse('2026-03-31'));

    expect($p->start->toDateString())->toBe('2026-02-01')
        ->and($p->end->toDateString())->toBe('2026-02-28');
});

it('compara com o mesmo período do ano anterior', function () use ($today) {
    $p = Period::fromPreset('mes_atual', $today)->previousYear();

    expect($p->start->toDateString())->toBe('2025-11-01')
        ->and($p->end->toDateString())->toBe('2025-11-13');
});

it('aceita período personalizado de um único dia', function () {
    $p = Period::custom('2026-05-07', '2026-05-07');

    expect($p->days())->toBe(1)
        ->and($p->label())->toBe('07/05/2026');
});

it('recusa data final anterior à inicial', function () {
    Period::custom('2026-05-10', '2026-05-01');
})->throws(InvalidArgumentException::class);

it('recusa preset desconhecido', function () {
    Period::fromPreset('semana_que_vem');
})->throws(InvalidArgumentException::class);

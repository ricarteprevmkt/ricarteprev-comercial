<?php

use App\Support\Cpf;

it('gera o mesmo hash para o CPF com ou sem máscara, sem expor o número', function () {
    $hash = Cpf::hash('529.982.247-25');

    expect($hash)->toBe(Cpf::hash('52998224725'))
        ->and($hash)->toHaveLength(64)
        ->and($hash)->not->toContain('52998224725')
        ->and(Cpf::hash(''))->toBeNull();
});

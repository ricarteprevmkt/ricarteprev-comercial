<?php

use App\Support\Cpf;
use App\Support\Money;
use App\Support\Phone;

it('valida CPF pelos dígitos verificadores', function (string $cpf, bool $valid) {
    expect(Cpf::isValid($cpf))->toBe($valid);
})->with([
    'válido com máscara' => ['529.982.247-25', true],
    'válido sem máscara' => ['52998224725', true],
    'dígito errado' => ['529.982.247-24', false],
    'repetido' => ['111.111.111-11', false],
    'curto' => ['1234567890', false],
    'vazio' => ['', false],
]);

it('formata e mascara CPF', function () {
    expect(Cpf::format('52998224725'))->toBe('529.982.247-25')
        ->and(Cpf::masked('529.982.247-25'))->toBe('***.***.***-25');
});

it('normaliza telefones brasileiros', function (string $input, ?string $expected) {
    expect(Phone::normalize($input))->toBe($expected);
})->with([
    'celular com máscara' => ['(88) 99876-5432', '88998765432'],
    'com DDI' => ['+55 88 99876-5432', '88998765432'],
    'fixo' => ['88 3512-3456', '8835123456'],
    'vazio' => ['', null],
]);

it('formata telefone e monta link do WhatsApp', function () {
    expect(Phone::format('88998765432'))->toBe('(88) 99876-5432')
        ->and(Phone::format('8835123456'))->toBe('(88) 3512-3456')
        ->and(Phone::whatsappUrl('(88) 99876-5432'))->toBe('https://wa.me/5588998765432')
        ->and(Phone::isValid('99876-5432'))->toBeFalse();
});

it('calcula percentual em dinheiro arredondando ao centavo', function () {
    expect(Money::percent('333.33', '5'))->toBe('16.67')
        ->and(Money::percent('1000', '5'))->toBe('50.00')
        ->and(Money::percent('100.10', '10'))->toBe('10.01')
        ->and(Money::sub('120', '70'))->toBe('50.00')
        ->and(Money::compare('10.00', '10'))->toBe(0);
});

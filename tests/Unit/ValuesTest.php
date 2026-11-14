<?php

declare(strict_types=1);

use MonteiroTi\Sei\Core\Support\SoapData;
use MonteiroTi\Sei\Core\Support\Values;
use MonteiroTi\Sei\Dto\Unidade;
use MonteiroTi\Sei\Enum\NivelAcesso;

it('converte booleanos para S/N e de volta', function () {
    expect(Values::sn(true))->toBe('S')
        ->and(Values::sn(false))->toBe('N')
        ->and(Values::sn(null))->toBeNull()
        ->and(Values::bool('S'))->toBeTrue()
        ->and(Values::bool('n'))->toBeFalse()
        ->and(Values::bool(''))->toBeNull();
});

it('formata e lê datas no padrão do SEI', function () {
    $date = new DateTimeImmutable('2026-03-05 14:07:09');

    expect(Values::date($date))->toBe('05/03/2026')
        ->and(Values::dateTime($date))->toBe('05/03/2026 14:07:09')
        ->and(Values::parseDate('05/03/2026')?->format('Y-m-d H:i:s'))->toBe('2026-03-05 00:00:00')
        ->and(Values::parseDate('05/03/2026 14:07:09')?->format('Y-m-d H:i:s'))->toBe('2026-03-05 14:07:09')
        ->and(Values::parseDate('05/03/2026 14:07')?->format('H:i'))->toBe('14:07')
        ->and(Values::parseDate('31/02/2026'))->toBeNull()
        ->and(Values::parseDate(''))->toBeNull();
});

it('mantém IDs grandes como string', function () {
    expect(Values::id(1210000000774))->toBe('1210000000774')
        ->and(Values::ids([1, '2']))->toBe(['1', '2']);
});

it('normaliza listas de um item, vazias e no formato {item: [...]}', function () {
    $one = (object) ['IdUnidade' => '1'];

    expect(SoapData::listOf(null))->toBe([])
        ->and(SoapData::listOf(''))->toBe([])
        ->and(SoapData::listOf($one))->toBe([$one])
        ->and(SoapData::listOf([$one, $one]))->toHaveCount(2)
        ->and(SoapData::listOf((object) ['item' => [$one, $one]]))->toHaveCount(2)
        ->and(SoapData::listOf(['IdUnidade' => '1']))->toBe([['IdUnidade' => '1']]);
});

it('lê campos tipados de stdClass', function () {
    $data = SoapData::from((object) [
        'IdUnidade' => 110000001,
        'Sigla' => '',
        'SinProtocolo' => 'S',
        'NivelAcesso' => '1',
        'Lista' => (object) ['IdUnidade' => '9', 'Sigla' => 'X'],
    ]);

    expect($data->id('IdUnidade'))->toBe('110000001')
        ->and($data->string('Sigla'))->toBeNull()
        ->and($data->bool('SinProtocolo'))->toBeTrue()
        ->and($data->enum('NivelAcesso', NivelAcesso::class))->toBe(NivelAcesso::Restrito)
        ->and($data->list('Lista', Unidade::class)[0]->sigla)->toBe('X')
        ->and($data->object('Inexistente', Unidade::class))->toBeNull();
});

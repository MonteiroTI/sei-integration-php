<?php

declare(strict_types=1);

use MonteiroTi\Sei\Core\Exceptions\InvalidArgumentException;
use MonteiroTi\Sei\Operations\SeiOperations;

it('injeta credenciais e unidade padrão e ordena os parâmetros como na WSDL', function () {
    $sei = fakeSei($t);
    $t->respond('concluirProcesso', true);

    $sei->call('concluirProcesso', ['ProtocoloProcedimento' => '12.1.000000077-4']);

    expect($t->lastArguments('concluirProcesso'))->toBe([
        'SiglaSistema' => 'SISTEMA',
        'IdentificacaoServico' => 'chave-secreta',
        'IdUnidade' => '110000001',
        'ProtocoloProcedimento' => '12.1.000000077-4',
    ]);
});

it('preenche com null os parâmetros omitidos, na ordem', function () {
    $sei = fakeSei($t);
    $t->respond('sobrestarProcesso', true);

    $sei->call('sobrestarProcesso', ['Motivo' => 'aguardando', 'ProtocoloProcedimento' => 'P']);

    expect(array_keys($t->lastArguments('sobrestarProcesso')))->toBe(SeiOperations::parameters('sobrestarProcesso'))
        ->and($t->lastArguments('sobrestarProcesso')['ProtocoloProcedimentoVinculado'])->toBeNull();
});

it('rejeita parâmetros desconhecidos e operações inexistentes', function () {
    $sei = fakeSei();

    expect(fn () => $sei->call('concluirProcesso', ['Protocolo' => 'x']))->toThrow(InvalidArgumentException::class, 'Protocolo')
        ->and(fn () => $sei->call('naoExiste'))->toThrow(\InvalidArgumentException::class);
});

it('exige unidade nas operações que a usam', function () {
    $sei = fakeSei($t, idUnidade: null);
    $t->respond('listarUnidades', []);

    expect(fn () => $sei->processos()->concluir('P'))->toThrow(InvalidArgumentException::class, 'naUnidade');

    $sei->catalogos()->unidades(); // listarUnidades não tem IdUnidade
    expect($t->lastArguments('listarUnidades'))->not->toHaveKey('IdUnidade');
});

it('naUnidade devolve um cliente em outra unidade sem alterar o original', function () {
    $sei = fakeSei($t);
    $t->respond('reabrirProcesso', true);

    $sei->naUnidade('999')->processos()->reabrir('P');
    $sei->processos()->reabrir('P');

    expect($t->calls('reabrirProcesso')[0]['arguments']['IdUnidade'])->toBe('999')
        ->and($t->calls('reabrirProcesso')[1]['arguments']['IdUnidade'])->toBe('110000001');
});

it('não injeta a unidade padrão em operações com unidade opcional', function () {
    $sei = fakeSei($t);
    $t->respond('listarSeries', []);

    $sei->catalogos()->series('100000368');

    expect($t->lastArguments('listarSeries')['IdUnidade'])->toBeNull();
});

it('cataloga as 66 operações do manual 5.0', function () {
    expect(SeiOperations::all())->toHaveCount(66)
        ->and(SeiOperations::parameters('confirmarDisponibilizacaoPublicacao')[2])->toBe('IdVeiculoPublicacao')
        ->and(SeiOperations::parameters('gerarProcedimento'))->toHaveCount(17);
});

<?php

declare(strict_types=1);

use MonteiroTi\Sei\Core\Config\CircuitOptions;
use MonteiroTi\Sei\Core\Exceptions\CircuitoAbertoException;
use MonteiroTi\Sei\Core\Exceptions\ConnectionException;
use MonteiroTi\Sei\Core\Exceptions\FaultException;
use MonteiroTi\Sei\Core\Transport\CircuitBreakerTransport;
use MonteiroTi\Sei\Core\Transport\FakeTransport;
use MonteiroTi\Sei\Core\Transport\InMemoryCircuitState;
use MonteiroTi\Sei\Core\Transport\RetryingTransport;
use MonteiroTi\Sei\SeiClient;

function circuito(FakeTransport $fake, int &$agora, int $falhas = 3): CircuitBreakerTransport
{
    return new CircuitBreakerTransport(
        $fake,
        new CircuitOptions(falhas: $falhas, janelaSegundos: 60, abertoSegundos: 30),
        new InMemoryCircuitState,
        'sei:default',
        function () use (&$agora): int {
            return $agora;
        },
    );
}

function falhar(CircuitBreakerTransport $c, string $op = 'listarUnidades'): ?Throwable
{
    try {
        $c->call($op, []);
    } catch (Throwable $e) {
        return $e;
    }

    return null;
}

it('abre depois de N falhas de conexão e falha rápido sem chamar o SEI', function () {
    $agora = 1000;
    $fake = (new FakeTransport)->respond('listarUnidades', new ConnectionException('Could not connect to host', 'listarUnidades'));
    $c = circuito($fake, $agora);

    falhar($c);
    falhar($c);
    expect($c->aberto())->toBeFalse();
    falhar($c);

    $e = falhar($c, 'gerarProcedimento');
    expect($c->aberto())->toBeTrue()
        ->and($c->abertoAte())->toBe(1030)
        ->and($e)->toBeInstanceOf(CircuitoAbertoException::class)
        ->and($e->possivelmenteEnviada)->toBeFalse()
        ->and($e->operation)->toBe('gerarProcedimento')
        ->and($fake->calls())->toHaveCount(3);
});

it('zera a contagem quando a janela passa', function () {
    $agora = 1000;
    $fake = (new FakeTransport)->respond('listarUnidades', new ConnectionException('x', 'listarUnidades'));
    $c = circuito($fake, $agora);

    falhar($c);
    falhar($c);
    $agora += 61;
    falhar($c);

    expect($c->aberto())->toBeFalse();
});

it('no meio-aberto fecha se a chamada de teste der certo', function () {
    $agora = 1000;
    $fake = (new FakeTransport)->respond('listarUnidades',
        new ConnectionException('x', 'listarUnidades'),
        new ConnectionException('x', 'listarUnidades'),
        new ConnectionException('x', 'listarUnidades'),
        [],
    );
    $c = circuito($fake, $agora);
    foreach (range(1, 3) as $_) {
        falhar($c);
    }

    $agora += 30;
    expect($c->call('listarUnidades', []))->toBe([])
        ->and($c->aberto())->toBeFalse()
        ->and($c->abertoAte())->toBeNull();
});

it('no meio-aberto reabre se a chamada de teste falhar', function () {
    $agora = 1000;
    $fake = (new FakeTransport)->respond('listarUnidades', new ConnectionException('x', 'listarUnidades'));
    $c = circuito($fake, $agora);
    foreach (range(1, 3) as $_) {
        falhar($c);
    }

    $agora += 31;
    expect(falhar($c))->not->toBeInstanceOf(CircuitoAbertoException::class);
    expect($c->abertoAte())->toBe(1061)
        ->and(falhar($c))->toBeInstanceOf(CircuitoAbertoException::class)
        ->and($fake->calls())->toHaveCount(4);
});

it('erros de negócio não contam como falha', function () {
    $agora = 1000;
    $fake = (new FakeTransport)->respond('consultarDocumento', new FaultException('Documento não encontrado.', 'consultarDocumento'));
    $c = circuito($fake, $agora, falhas: 1);

    expect(falhar($c, 'consultarDocumento'))->toBeInstanceOf(FaultException::class)
        ->and($c->aberto())->toBeFalse();
});

it('pode ser fechado manualmente', function () {
    $agora = 1000;
    $fake = (new FakeTransport)->respond('listarUnidades', new ConnectionException('x', 'listarUnidades'));
    $c = circuito($fake, $agora, falhas: 1);
    falhar($c);

    $c->fechar();

    expect($c->aberto())->toBeFalse();
});

it('fora do Laravel o circuito fica desligado por padrão', function () {
    $config = ['wsdl' => seiFixture('sei-mini.wsdl'), 'sigla_sistema' => 'S', 'identificacao_servico' => 'k'];

    expect(SeiClient::create($config)->circuito())->toBeNull();

    $sei = SeiClient::create($config + ['circuito' => true]);
    expect($sei->circuito())->toBeInstanceOf(CircuitBreakerTransport::class)
        ->and($sei->circuito()?->inner())->toBeInstanceOf(RetryingTransport::class)
        ->and(CircuitOptions::fromConfig(false, true))->toBeNull()
        ->and(CircuitOptions::fromConfig(null, true))->toEqual(new CircuitOptions)
        ->and(CircuitOptions::fromConfig(['falhas' => 2], false)?->falhas)->toBe(2);
});

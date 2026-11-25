<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use MonteiroTi\Sei\Core\Exceptions\CircuitoAbertoException;
use MonteiroTi\Sei\Core\Exceptions\ConnectionException;
use MonteiroTi\Sei\Core\Transport\CircuitBreakerTransport;
use MonteiroTi\Sei\Core\Transport\FakeTransport;
use MonteiroTi\Sei\Core\Transport\RetryingTransport;
use MonteiroTi\Sei\Dto\Procedimento;
use MonteiroTi\Sei\Laravel\Events\SeiRequestFailed;
use MonteiroTi\Sei\Laravel\Facades\Sei;
use MonteiroTi\Sei\Laravel\Models\SeiOperacao;
use MonteiroTi\Sei\Laravel\Models\StatusOperacao;
use MonteiroTi\Sei\Laravel\ObservedTransport;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTi\Sei\Tests\Support\Requerimento;
use Psr\Log\LoggerInterface;

/** Conexão que recusa a conexão TCP na hora (porta 1 local). */
function conexaoRecusada(array $extra = []): void
{
    config(['sei.connections.recusada' => array_merge(config('sei.connections.default'), [
        'location' => 'http://127.0.0.1:1/sei/controlador_ws.php',
        'connection_timeout' => 1,
        'retry' => ['tentativas' => 2, 'espera_ms' => 0],
    ], $extra)]);
}

it('monta Circuito(Retry(Observed(Soap))) e repete leituras com log e evento por tentativa', function () {
    conexaoRecusada();
    config(['sei.log.enabled' => true]);
    Event::fake([SeiRequestFailed::class]);
    $logger = Mockery::spy(LoggerInterface::class);
    Log::shouldReceive('channel')->andReturn($logger);

    $sei = Sei::connection('recusada');
    expect($sei->circuito())->toBeInstanceOf(CircuitBreakerTransport::class)
        ->and($sei->circuito()?->inner())->toBeInstanceOf(RetryingTransport::class)
        ->and($sei->circuito()?->inner()->inner())->toBeInstanceOf(ObservedTransport::class);

    expect(fn () => $sei->catalogos(cache: false)->unidades())->toThrow(ConnectionException::class);

    Event::assertDispatchedTimes(SeiRequestFailed::class, 3);
    $logger->shouldHaveReceived('warning')->withArgs(fn (string $msg) => $msg === 'SEI listarUnidades: nova tentativa 2')->once();
});

it('não repete escritas nem quando a conexão foi recusada', function () {
    conexaoRecusada();
    Event::fake([SeiRequestFailed::class]);

    expect(fn () => Sei::connection('recusada')->processos()->enviar('P', ['1']))->toThrow(ConnectionException::class);

    Event::assertDispatchedTimes(SeiRequestFailed::class, 1);
});

it('retry pode ser desligado por conexão e não existe com o fake', function () {
    conexaoRecusada(['retry' => false, 'circuito' => false]);

    expect(Sei::connection('recusada')->transport())->toBeInstanceOf(ObservedTransport::class);

    Sei::fake();
    expect(Sei::connection()->transport())->toBeInstanceOf(ObservedTransport::class)
        ->and(Sei::connection()->transport()->inner())->toBeInstanceOf(FakeTransport::class)
        ->and(Sei::connection()->circuito())->toBeNull();
});

it('abre o circuito no cache, compartilhado entre clientes da mesma conexão', function () {
    conexaoRecusada(['retry' => false, 'circuito' => ['falhas' => 2, 'janela_segundos' => 60, 'aberto_segundos' => 30]]);

    foreach (range(1, 2) as $_) {
        expect(fn () => Sei::connection('recusada')->catalogos(cache: false)->unidades())->toThrow(ConnectionException::class);
    }

    app(SeiManager::class)->purge();
    expect(fn () => Sei::connection('recusada')->catalogos(cache: false)->unidades())->toThrow(CircuitoAbertoException::class)
        ->and(app(SeiManager::class)->circuitos(['recusada', 'default']))->toHaveKeys(['recusada', 'default'])
        ->and(app(SeiManager::class)->circuitos()['recusada']->aberto())->toBeTrue()
        ->and(app(SeiManager::class)->circuitos()['default']->aberto())->toBeFalse();
});

it('com o circuito aberto a idempotência registra falha, não operação incerta', function () {
    conexaoRecusada(['circuito' => ['falhas' => 1]]);
    Sei::connection('recusada')->circuito()?->fechar();
    expect(fn () => Sei::connection('recusada')->processos()->enviar('P', ['1']))->toThrow(ConnectionException::class);

    $requerimento = Requerimento::create(['assunto' => 'férias']);
    expect(fn () => Sei::integracao('recusada')->gerarProcesso($requerimento, new Procedimento('100000368', 'x')))
        ->toThrow(CircuitoAbertoException::class);

    $operacao = SeiOperacao::query()->firstOrFail();
    expect($operacao->status)->toBe(StatusOperacao::Falhou);
});

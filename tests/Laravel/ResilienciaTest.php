<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use MonteiroTi\Sei\Core\Exceptions\ConnectionException;
use MonteiroTi\Sei\Core\Transport\FakeTransport;
use MonteiroTi\Sei\Core\Transport\RetryingTransport;
use MonteiroTi\Sei\Laravel\Events\SeiRequestFailed;
use MonteiroTi\Sei\Laravel\Facades\Sei;
use MonteiroTi\Sei\Laravel\ObservedTransport;
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

it('monta Retry(Observed(Soap)) e repete leituras com log e evento por tentativa', function () {
    conexaoRecusada();
    config(['sei.log.enabled' => true]);
    Event::fake([SeiRequestFailed::class]);
    $logger = Mockery::spy(LoggerInterface::class);
    Log::shouldReceive('channel')->andReturn($logger);

    $sei = Sei::connection('recusada');
    expect($sei->transport())->toBeInstanceOf(RetryingTransport::class)
        ->and($sei->transport()->inner())->toBeInstanceOf(ObservedTransport::class);

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
    conexaoRecusada(['retry' => false]);

    expect(Sei::connection('recusada')->transport())->toBeInstanceOf(ObservedTransport::class);

    Sei::fake();
    expect(Sei::connection()->transport())->not->toBeInstanceOf(RetryingTransport::class)
        ->and(Sei::connection()->transport()->inner())->toBeInstanceOf(FakeTransport::class);
});

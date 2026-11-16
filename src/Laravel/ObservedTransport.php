<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel;

use Illuminate\Contracts\Events\Dispatcher;
use MonteiroTi\Sei\Core\Exceptions\SeiException;
use MonteiroTi\Sei\Core\Support\Redactor;
use MonteiroTi\Sei\Core\Transport\Transport;
use MonteiroTi\Sei\Laravel\Events\SeiRequestFailed;
use MonteiroTi\Sei\Laravel\Events\SeiRequestSending;
use MonteiroTi\Sei\Laravel\Events\SeiResponseReceived;
use Psr\Log\LoggerInterface;

/**
 * Decorator que registra logs e dispara eventos a cada chamada.
 *
 * Logs e SeiRequestSending recebem argumentos mascarados (credenciais e dados pessoais).
 * SeiResponseReceived e SeiRequestFailed trazem o retorno e a exceção originais,
 * para uso interno da aplicação: não os registre sem mascarar.
 */
final class ObservedTransport implements Transport
{
    public function __construct(
        private readonly Transport $inner,
        private readonly string $connection,
        private readonly ?Dispatcher $events = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?Redactor $redactor = null,
    ) {}

    public function call(string $operation, array $arguments): mixed
    {
        $redactor = $this->redactor ?? Redactor::default();
        $redacted = $redactor->arguments($arguments, $operation);
        $this->events?->dispatch(new SeiRequestSending($this->connection, $operation, $redacted));
        $start = hrtime(true);

        try {
            $result = $this->inner->call($operation, $arguments);
        } catch (SeiException $e) {
            $ms = $this->elapsed($start);
            $this->logger?->warning("SEI {$operation} falhou", [
                'connection' => $this->connection,
                'duration_ms' => $ms,
                'exception' => $e::class,
                'message' => $redactor->text($e->getMessage()),
                'arguments' => $redacted,
            ]);
            $this->events?->dispatch(new SeiRequestFailed($this->connection, $operation, $ms, $e));

            throw $e;
        }

        $ms = $this->elapsed($start);
        $this->logger?->info("SEI {$operation}", [
            'connection' => $this->connection,
            'duration_ms' => $ms,
            'arguments' => $redacted,
        ]);
        $this->events?->dispatch(new SeiResponseReceived($this->connection, $operation, $ms, $result));

        return $result;
    }

    public function inner(): Transport
    {
        return $this->inner;
    }

    private function elapsed(int|float $start): float
    {
        return round((hrtime(true) - $start) / 1e6, 2);
    }
}

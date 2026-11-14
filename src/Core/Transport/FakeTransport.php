<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Transport;

use Closure;
use MonteiroTi\Sei\Core\Exceptions\SeiException;

/**
 * Transporte em memória para testes: devolve respostas programadas e registra as chamadas.
 */
final class FakeTransport implements Transport
{
    /** @var array<string, list<mixed>> */
    private array $queues = [];

    /** @var array<string, mixed> */
    private array $defaults = [];

    /** @var list<array{operation: string, arguments: array<string, mixed>}> */
    private array $calls = [];

    /**
     * Programa a resposta de uma operação. Respostas enfileiradas são consumidas em ordem;
     * a última passa a ser a resposta padrão. Pode ser um valor, uma Closure(array $args)
     * ou uma exceção (que será lançada).
     */
    public function respond(string $operation, mixed ...$responses): self
    {
        foreach ($responses as $response) {
            $this->queues[$operation][] = $response;
            $this->defaults[$operation] = $response;
        }

        return $this;
    }

    public function call(string $operation, array $arguments): mixed
    {
        $this->calls[] = ['operation' => $operation, 'arguments' => $arguments];

        if (! empty($this->queues[$operation])) {
            $response = array_shift($this->queues[$operation]);
        } elseif (array_key_exists($operation, $this->defaults)) {
            $response = $this->defaults[$operation];
        } else {
            throw new SeiException(sprintf('FakeTransport: nenhuma resposta programada para "%s".', $operation), $operation);
        }

        if ($response instanceof \Throwable) {
            throw $response;
        }

        return $response instanceof Closure ? $response($arguments) : $response;
    }

    /**
     * @return list<array{operation: string, arguments: array<string, mixed>}>
     */
    public function calls(?string $operation = null): array
    {
        if ($operation === null) {
            return $this->calls;
        }

        return array_values(array_filter($this->calls, fn (array $c) => $c['operation'] === $operation));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastArguments(string $operation): ?array
    {
        $calls = $this->calls($operation);

        return $calls === [] ? null : end($calls)['arguments'];
    }

    public function reset(): void
    {
        $this->queues = $this->defaults = $this->calls = [];
    }
}

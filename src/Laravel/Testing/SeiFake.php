<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Testing;

use Closure;
use MonteiroTi\Sei\Core\Transport\FakeTransport;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Fake para testes de aplicações: Sei::fake()->respond('gerarProcedimento', [...]).
 *
 * As respostas são o que o \SoapClient devolveria (arrays/stdClass com os nomes do SEI),
 * ou Closures que recebem os argumentos nomeados, ou exceções a lançar.
 */
final class SeiFake
{
    public function __construct(private readonly FakeTransport $transport) {}

    public function respond(string $operation, mixed ...$responses): self
    {
        $this->transport->respond($operation, ...$responses);

        return $this;
    }

    /**
     * @param  (Closure(array<string, mixed>): bool)|null  $callback
     */
    public function assertCalled(string $operation, ?Closure $callback = null): self
    {
        $calls = $this->transport->calls($operation);
        PHPUnit::assertNotEmpty($calls, "A operação SEI [{$operation}] não foi chamada.");

        if ($callback !== null) {
            $matched = array_filter($calls, fn (array $c) => $callback($c['arguments']) === true);
            PHPUnit::assertNotEmpty($matched, "A operação SEI [{$operation}] foi chamada, mas nenhuma chamada satisfez a condição.");
        }

        return $this;
    }

    public function assertCalledTimes(string $operation, int $times): self
    {
        $count = count($this->transport->calls($operation));
        PHPUnit::assertSame($times, $count, "A operação SEI [{$operation}] foi chamada {$count} vez(es), esperado {$times}.");

        return $this;
    }

    public function assertNotCalled(string $operation): self
    {
        PHPUnit::assertEmpty($this->transport->calls($operation), "A operação SEI [{$operation}] foi chamada inesperadamente.");

        return $this;
    }

    public function assertNothingCalled(): self
    {
        PHPUnit::assertEmpty($this->transport->calls(), 'Houve chamadas ao SEI/SIP inesperadas.');

        return $this;
    }

    public function transport(): FakeTransport
    {
        return $this->transport;
    }
}

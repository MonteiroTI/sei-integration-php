<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Testing;

use Closure;
use MonteiroTi\Sei\Core\Transport\FakeTransport;
use MonteiroTi\Sei\Testing\SeiSimulado;
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

    /**
     * Programa respostas. Use os formatos de MonteiroTi\Sei\Testing\Respostas para não
     * depender dos nomes do SEI: respond('gerarProcedimento', Respostas::processoGerado(...)).
     */
    public function respond(string $operation, mixed ...$responses): self
    {
        $this->transport->respond($operation, ...$responses);

        return $this;
    }

    /**
     * Responde com um SEI em memória (SeiSimulado) as operações sem resposta programada.
     */
    public function simular(?SeiSimulado $sei = null): self
    {
        $this->transport->simular($sei);

        return $this;
    }

    /** O SEI simulado, para mudar o estado nos testes (tramitar, assinar, concluir...). */
    public function sei(): SeiSimulado
    {
        return $this->transport->simulado()
            ?? throw new \LogicException('Chame simular() antes de usar sei().');
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

    /**
     * As operações foram chamadas nesta ordem (outras chamadas podem existir entre elas).
     *
     * @param  list<string>  $operations
     */
    public function assertCalledInOrder(array $operations): self
    {
        $chamadas = array_column($this->transport->calls(), 'operation');
        $pos = 0;
        foreach ($chamadas as $chamada) {
            if ($pos < count($operations) && $chamada === $operations[$pos]) {
                $pos++;
            }
        }

        PHPUnit::assertSame(count($operations), $pos, sprintf(
            'As operações SEI não foram chamadas na ordem [%s]. Chamadas: [%s].',
            implode(', ', $operations),
            implode(', ', $chamadas),
        ));

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

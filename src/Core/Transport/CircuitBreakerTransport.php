<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Transport;

use Closure;
use MonteiroTi\Sei\Core\Config\CircuitOptions;
use MonteiroTi\Sei\Core\Exceptions\CircuitoAbertoException;
use MonteiroTi\Sei\Core\Exceptions\ConnectionException;

/**
 * Circuit breaker por conexão.
 *
 * - fechado: as chamadas passam; falhas de conexão são contadas na janela;
 * - aberto: depois de N falhas, as chamadas falham na hora com CircuitoAbertoException
 *   (possivelmenteEnviada = false, então podem ser repetidas com segurança);
 * - meio-aberto: passado o tempo aberto, uma chamada de teste passa. Se der certo o
 *   circuito fecha; se falhar, abre de novo.
 *
 * Erros de negócio (FaultException etc.) não contam: o SEI está respondendo.
 * Fica na camada mais externa, então conta uma falha por chamada (depois dos retries).
 */
final class CircuitBreakerTransport implements Transport
{
    /** @var Closure(): int */
    private Closure $agora;

    /**
     * @param  string  $chave  Identifica a conexão no armazenamento (ex.: sei:default)
     * @param  (Closure(): int)|null  $agora  Relógio em segundos (padrão: time)
     */
    public function __construct(
        private readonly Transport $inner,
        private readonly CircuitOptions $options,
        private readonly CircuitState $state,
        private readonly string $chave,
        ?Closure $agora = null,
    ) {
        $this->agora = $agora ?? time(...);
    }

    public function call(string $operation, array $arguments): mixed
    {
        $estado = $this->state->ler($this->chave);
        $agora = ($this->agora)();

        if ($estado !== null && $estado['abertoAte'] !== null) {
            if ($agora < $estado['abertoAte']) {
                throw new CircuitoAbertoException($this->chave, $estado['abertoAte'], $operation);
            }

            // Meio-aberto: esta chamada é o teste; as demais continuam falhando rápido enquanto ela roda.
            $estado['abertoAte'] = $agora + $this->options->abertoSegundos;
            $this->gravar($estado);
        }

        try {
            $resultado = $this->inner->call($operation, $arguments);
        } catch (ConnectionException $e) {
            $this->falhou($estado, $agora);

            throw $e;
        }

        if ($estado !== null) {
            $this->state->limpar($this->chave);
        }

        return $resultado;
    }

    /** Indica se as chamadas estão suspensas agora. */
    public function aberto(): bool
    {
        $ate = $this->abertoAte();

        return $ate !== null && ($this->agora)() < $ate;
    }

    /** Timestamp Unix até quando o circuito fica aberto (null: fechado). */
    public function abertoAte(): ?int
    {
        return $this->state->ler($this->chave)['abertoAte'] ?? null;
    }

    /** Fecha o circuito manualmente (ex.: depois de confirmar que o SEI voltou). */
    public function fechar(): void
    {
        $this->state->limpar($this->chave);
    }

    public function chave(): string
    {
        return $this->chave;
    }

    public function inner(): Transport
    {
        return $this->inner;
    }

    /**
     * @param  array{falhas: int, desde: int, abertoAte: int|null}|null  $estado
     */
    private function falhou(?array $estado, int $agora): void
    {
        if ($estado !== null && $estado['abertoAte'] !== null) {
            // O teste do meio-aberto falhou: abre de novo.
            $this->gravar(['falhas' => $estado['falhas'], 'desde' => $estado['desde'], 'abertoAte' => $agora + $this->options->abertoSegundos]);

            return;
        }

        if ($estado === null || $agora - $estado['desde'] >= $this->options->janelaSegundos) {
            $estado = ['falhas' => 0, 'desde' => $agora, 'abertoAte' => null];
        }

        $estado['falhas']++;
        if ($estado['falhas'] >= $this->options->falhas) {
            $estado['abertoAte'] = $agora + $this->options->abertoSegundos;
        }

        $this->gravar($estado);
    }

    /**
     * @param  array{falhas: int, desde: int, abertoAte: int|null}  $estado
     */
    private function gravar(array $estado): void
    {
        $this->state->gravar($this->chave, $estado, $this->options->janelaSegundos + $this->options->abertoSegundos);
    }
}

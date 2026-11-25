<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Jobs\Concerns;

use Closure;
use Illuminate\Queue\Jobs\SyncJob;
use MonteiroTi\Sei\Core\Exceptions\ConnectionException;
use MonteiroTi\Sei\Core\Exceptions\SeiException;
use MonteiroTi\Sei\Laravel\Integracao\OperacaoEmAndamentoException;
use MonteiroTi\Sei\Laravel\Integracao\OperacaoIncertaException;

/**
 * Decide o que fazer com cada falha de um job de escrita que passa pela idempotência.
 *
 * - OperacaoIncertaException: falha de vez; exige conferência manual (sei:operacoes);
 * - ConnectionException: devolve à fila com backoff. Ela só chega aqui quando é seguro
 *   repetir (a idempotência converte as duvidosas em OperacaoIncertaException); inclui
 *   CircuitoAbertoException;
 * - OperacaoEmAndamentoException: outra execução tem o lock; tenta mais tarde;
 * - demais SeiException (erro de negócio, acesso negado, dados inválidos): falha de vez.
 *
 * Esgotadas as tentativas, o job falha com a última exceção. Fora de uma fila assíncrona
 * (dispatchSync, conexão sync) a exceção é relançada, como antes.
 */
trait TrataFalhasSei
{
    /**
     * Esperas (s) entre as tentativas.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300, 900];
    }

    /** Nome da conexão usada pelo job (a padrão quando não informada). */
    protected function conexaoResolvida(): string
    {
        return $this->conexao ?? (string) config('sei.default', 'default');
    }

    protected function timeoutDaConexao(): int
    {
        return (int) config('sei.connections.'.$this->conexaoResolvida().'.timeout', 60) + 60;
    }

    /**
     * @param  Closure(): mixed  $operacao
     */
    protected function executarNoSei(Closure $operacao): void
    {
        try {
            $operacao();
        } catch (SeiException $e) {
            if ($this->job === null || $this->job instanceof SyncJob) {
                throw $e;
            }

            match (true) {
                $e instanceof OperacaoIncertaException => $this->fail($e),
                $e instanceof ConnectionException, $e instanceof OperacaoEmAndamentoException => $this->tentarDeNovo($e),
                default => $this->fail($e),
            };
        }
    }

    private function tentarDeNovo(SeiException $e): void
    {
        $tentativa = max(1, $this->attempts());
        if ($tentativa >= $this->tries) {
            $this->fail($e);

            return;
        }

        $esperas = $this->backoff();
        $this->release($esperas[$tentativa - 1] ?? $esperas[array_key_last($esperas)] ?? 60);
    }
}

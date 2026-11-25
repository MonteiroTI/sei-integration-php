<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Transport;

use Closure;
use MonteiroTi\Sei\Core\Exceptions\AccessDeniedException;
use MonteiroTi\Sei\Core\Exceptions\ConnectionException;
use MonteiroTi\Sei\Core\Exceptions\FaultException;
use MonteiroTi\Sei\Core\Exceptions\SeiException;
use MonteiroTi\Sei\Core\Exceptions\ValidationException;
use MonteiroTi\Sei\Testing\SeiSimulado;

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

    private ?SeiSimulado $simulado = null;

    /**
     * Usa um SEI em memória para as operações sem resposta programada.
     * Respostas programadas com respond() continuam tendo prioridade.
     */
    public function simular(?SeiSimulado $sei = null): SeiSimulado
    {
        return $this->simulado = $sei ?? $this->simulado ?? new SeiSimulado;
    }

    public function simulado(): ?SeiSimulado
    {
        return $this->simulado;
    }

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

    /**
     * Programa as respostas gravadas com GravadorTransport, na ordem em que foram gravadas.
     * Erros gravados viram a exceção correspondente.
     */
    public function reproduzir(string $diretorio): self
    {
        $arquivos = glob(rtrim($diretorio, '/').'/*.json') ?: [];
        if ($arquivos === []) {
            throw new \InvalidArgumentException("Nenhuma gravação encontrada em {$diretorio}.");
        }
        sort($arquivos);

        foreach ($arquivos as $arquivo) {
            $registro = json_decode((string) file_get_contents($arquivo), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($registro) || ! is_string($registro['operacao'] ?? null)) {
                throw new \InvalidArgumentException("Gravação inválida: {$arquivo}.");
            }

            $operacao = $registro['operacao'];
            $erro = $registro['erro'] ?? null;
            $this->respond($operacao, is_array($erro) ? self::excecao($erro, $operacao) : ($registro['resposta'] ?? null));
        }

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $erro
     */
    private static function excecao(array $erro, string $operacao): SeiException
    {
        $mensagem = is_string($erro['mensagem'] ?? null) ? $erro['mensagem'] : 'Erro gravado.';
        $classe = $erro['classe'] ?? null;

        return match (true) {
            $classe === FaultException::class => new FaultException($mensagem, $operacao),
            $classe === AccessDeniedException::class => new AccessDeniedException($mensagem, $operacao),
            $classe === ValidationException::class => new ValidationException($mensagem, $operacao),
            is_string($classe) && is_a($classe, ConnectionException::class, true) => new ConnectionException($mensagem, $operacao),
            default => new SeiException($mensagem, $operacao),
        };
    }

    public function call(string $operation, array $arguments): mixed
    {
        $this->calls[] = ['operation' => $operation, 'arguments' => $arguments];

        if (! empty($this->queues[$operation])) {
            $response = array_shift($this->queues[$operation]);
        } elseif (array_key_exists($operation, $this->defaults)) {
            $response = $this->defaults[$operation];
        } elseif ($this->simulado !== null) {
            return $this->simulado->call($operation, $arguments);
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
        $this->simulado = null;
    }
}

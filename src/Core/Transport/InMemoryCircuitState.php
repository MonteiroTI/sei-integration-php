<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Transport;

/** Estado do circuito em memória do processo (workers longos, CLI, testes). */
final class InMemoryCircuitState implements CircuitState
{
    /** @var array<string, array{falhas: int, desde: int, abertoAte: int|null}> */
    private array $estados = [];

    public function ler(string $chave): ?array
    {
        return $this->estados[$chave] ?? null;
    }

    public function gravar(string $chave, array $estado, int $ttl): void
    {
        $this->estados[$chave] = $estado;
    }

    public function limpar(string $chave): void
    {
        unset($this->estados[$chave]);
    }
}

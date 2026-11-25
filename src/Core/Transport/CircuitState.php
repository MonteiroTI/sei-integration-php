<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Transport;

/**
 * Onde o circuit breaker guarda o estado de cada conexão.
 *
 * Em PHP-FPM o estado em memória é zerado a cada requisição: use um armazenamento
 * compartilhado (no Laravel, o cache) para que todos os workers vejam o mesmo circuito.
 */
interface CircuitState
{
    /**
     * @return array{falhas: int, desde: int, abertoAte: int|null}|null
     */
    public function ler(string $chave): ?array;

    /**
     * @param  array{falhas: int, desde: int, abertoAte: int|null}  $estado
     * @param  int  $ttl  Segundos que o estado precisa durar
     */
    public function gravar(string $chave, array $estado, int $ttl): void;

    public function limpar(string $chave): void;
}

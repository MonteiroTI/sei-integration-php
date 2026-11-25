<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel;

use Illuminate\Contracts\Cache\Repository;
use MonteiroTi\Sei\Core\Transport\CircuitState;

/** Estado do circuito no cache do Laravel, compartilhado entre workers e servidores. */
final class CacheCircuitState implements CircuitState
{
    public function __construct(
        private readonly Repository $cache,
        private readonly string $prefixo = 'sei:circuito',
    ) {}

    public function ler(string $chave): ?array
    {
        $estado = $this->cache->get($this->key($chave));

        if (! is_array($estado) || ! isset($estado['falhas'], $estado['desde'])) {
            return null;
        }

        return [
            'falhas' => (int) $estado['falhas'],
            'desde' => (int) $estado['desde'],
            'abertoAte' => isset($estado['abertoAte']) ? (int) $estado['abertoAte'] : null,
        ];
    }

    public function gravar(string $chave, array $estado, int $ttl): void
    {
        $this->cache->put($this->key($chave), $estado, $ttl);
    }

    public function limpar(string $chave): void
    {
        $this->cache->forget($this->key($chave));
    }

    private function key(string $chave): string
    {
        return $this->prefixo.':'.$chave;
    }
}

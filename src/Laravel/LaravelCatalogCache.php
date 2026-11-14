<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use MonteiroTi\Sei\Core\Support\CatalogCache;

final class LaravelCatalogCache implements CatalogCache
{
    public function __construct(
        private readonly Repository $cache,
        private readonly int $ttl,
        private readonly string $prefix = 'sei',
    ) {}

    public function remember(string $key, Closure $callback): mixed
    {
        return $this->cache->remember($this->prefix.':catalogo:'.$key, $this->ttl, $callback);
    }
}

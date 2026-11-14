<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Support;

use Closure;

/**
 * Cache opcional para as operações listar* (tabelas que mudam pouco).
 */
interface CatalogCache
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function remember(string $key, Closure $callback): mixed;
}

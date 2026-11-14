<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Events;

final class SeiRequestSending
{
    /**
     * @param  array<string, mixed>  $arguments  Argumentos já mascarados
     */
    public function __construct(
        public readonly string $connection,
        public readonly string $operation,
        public readonly array $arguments,
    ) {}
}

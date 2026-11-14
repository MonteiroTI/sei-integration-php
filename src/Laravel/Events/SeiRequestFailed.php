<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Events;

use MonteiroTi\Sei\Core\Exceptions\SeiException;

final class SeiRequestFailed
{
    public function __construct(
        public readonly string $connection,
        public readonly string $operation,
        public readonly float $durationMs,
        public readonly SeiException $exception,
    ) {}
}

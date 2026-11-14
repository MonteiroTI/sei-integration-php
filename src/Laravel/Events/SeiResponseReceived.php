<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Events;

final class SeiResponseReceived
{
    public function __construct(
        public readonly string $connection,
        public readonly string $operation,
        public readonly float $durationMs,
        public readonly mixed $result,
    ) {}
}

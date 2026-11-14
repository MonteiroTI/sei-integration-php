<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Exceptions;

/**
 * Exceção base de todos os erros vindos do SEI/SIP ou da comunicação com eles.
 */
class SeiException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $operation = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}

<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Integracao;

use MonteiroTi\Sei\Core\Exceptions\SeiException;

/**
 * A operação pode ter sido executada no SEI (ex.: timeout após o envio) e não pode ser
 * repetida automaticamente. Confira no SEI e resolva com `php artisan sei:operacoes:resolver`.
 */
class OperacaoIncertaException extends SeiException
{
    public function __construct(
        public readonly string $chave,
        string $operation,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'A operação %s (chave "%s") pode ter sido executada no SEI. Confira e resolva com: php artisan sei:operacoes:resolver "%s".',
                $operation,
                $chave,
                $chave,
            ),
            $operation,
            0,
            $previous,
        );
    }
}

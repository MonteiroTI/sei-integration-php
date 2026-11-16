<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Integracao;

use MonteiroTi\Sei\Core\Exceptions\SeiException;

/**
 * Outra execução com a mesma chave está em andamento.
 */
class OperacaoEmAndamentoException extends SeiException
{
    public function __construct(public readonly string $chave, string $operation)
    {
        parent::__construct(sprintf('A operação %s com a chave "%s" já está em execução.', $operation, $chave), $operation);
    }
}

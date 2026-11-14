<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Exceptions;

/**
 * Erros de validação acumulados (ex.: atualizarContatos, replicarUsuario, replicarPermissao).
 */
class ValidationException extends FaultException
{
    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->messages();
    }
}

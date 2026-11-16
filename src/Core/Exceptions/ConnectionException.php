<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Exceptions;

/**
 * Falha de rede, timeout ou WSDL inacessível.
 */
class ConnectionException extends SeiException
{
    /**
     * @param  bool  $possivelmenteEnviada  A requisição pode ter chegado ao SEI (ex.: timeout esperando a resposta).
     *                                      Em operações não idempotentes, repetir pode duplicar o efeito.
     */
    public function __construct(
        string $message,
        ?string $operation = null,
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly bool $possivelmenteEnviada = false,
    ) {
        parent::__construct($message, $operation, $code, $previous);
    }
}

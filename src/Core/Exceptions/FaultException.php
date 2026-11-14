<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Exceptions;

/**
 * Erro devolvido pelo SEI/SIP como SOAP Fault (regra de negócio, validação, permissão).
 */
class FaultException extends SeiException
{
    public function __construct(
        string $message,
        ?string $operation = null,
        public readonly ?string $faultCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $operation, 0, $previous);
    }

    /**
     * Mensagens individuais (o SEI acumula erros separados por quebra de linha).
     *
     * @return list<string>
     */
    public function messages(): array
    {
        $lines = preg_split('/\R+/u', $this->getMessage()) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn (string $l) => $l !== ''));
    }
}

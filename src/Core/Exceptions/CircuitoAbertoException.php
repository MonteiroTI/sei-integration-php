<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Exceptions;

/**
 * A conexão teve falhas seguidas e o circuito está aberto: a chamada nem foi enviada.
 * Pode ser repetida depois de $abertoAte (timestamp Unix).
 */
final class CircuitoAbertoException extends ConnectionException
{
    public function __construct(
        public readonly string $conexao,
        public readonly int $abertoAte,
        ?string $operation = null,
    ) {
        parent::__construct(
            sprintf(
                'O SEI não respondeu nas últimas chamadas da conexão %s: novas chamadas estão suspensas até %s.',
                $conexao,
                date('H:i:s', $abertoAte),
            ),
            $operation,
            possivelmenteEnviada: false,
        );
    }
}

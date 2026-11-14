<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Enum;

/**
 * Estado do bloco.
 */
enum EstadoBloco: string
{
    case Aberto = 'A';
    case Disponibilizado = 'D';
    case Recebido = 'B';
    case Retornado = 'R';
    case Concluido = 'C';
}

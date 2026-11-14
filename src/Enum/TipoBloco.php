<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Enum;

/**
 * Tipo de bloco.
 */
enum TipoBloco: string
{
    case Assinatura = 'A';
    case Reuniao = 'R';
    case Interno = 'I';
}

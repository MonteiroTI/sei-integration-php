<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Enum;

/**
 * Tipo do veículo de publicação.
 */
enum TipoVeiculo: string
{
    case Interno = 'I';
    case Externo = 'E';
    case Modulo = 'M';
}

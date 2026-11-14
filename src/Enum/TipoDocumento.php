<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Enum;

/**
 * Tipo do documento: gerado no editor do SEI ou recebido (externo).
 */
enum TipoDocumento: string
{
    case Gerado = 'G';
    case Externo = 'R';
}

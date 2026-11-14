<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Enum;

/**
 * Estado de uma publicação.
 */
enum EstadoPublicacao: string
{
    case Agendado = 'A';
    case Publicado = 'P';
}

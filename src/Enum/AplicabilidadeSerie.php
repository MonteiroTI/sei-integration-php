<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Enum;

/**
 * Aplicabilidade de um tipo de documento (série).
 */
enum AplicabilidadeSerie: string
{
    case Todos = 'T';
    case Internos = 'I';
    case Externos = 'E';
    case Formularios = 'F';
}

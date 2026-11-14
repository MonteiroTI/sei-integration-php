<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Enum;

/**
 * Nível de acesso de processos e documentos.
 */
enum NivelAcesso: int
{
    case Publico = 0;
    case Restrito = 1;
    case Sigiloso = 2;
}

<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Enum;

/**
 * Motivo (StaMotivo) de uma publicação.
 */
enum MotivoPublicacao: int
{
    case Publicacao = 1;
    case Retificacao = 2;
    case Republicacao = 3;
    case Apostilamento = 4;
}

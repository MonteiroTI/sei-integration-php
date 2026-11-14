<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Enum;

/**
 * Natureza do contato.
 */
enum Natureza: string
{
    case PessoaFisica = 'F';
    case PessoaJuridica = 'J';
}

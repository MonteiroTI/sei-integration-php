<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Sip\Enum;

/** StaOperacao da estrutura Permissao do SIP. */
enum OperacaoPermissao: string
{
    case Atualizar = 'A';
    case Excluir = 'E';
}

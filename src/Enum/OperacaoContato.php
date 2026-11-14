<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Enum;

/**
 * Operação (StaOperacao) em atualizarContatos.
 */
enum OperacaoContato: string
{
    case Atualizar = 'A';
    case Excluir = 'E';
    case Desativar = 'D';
    case Reativar = 'R';
}

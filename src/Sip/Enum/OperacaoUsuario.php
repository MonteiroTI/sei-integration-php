<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Sip\Enum;

/** StaOperacao da estrutura Usuario do SIP. */
enum OperacaoUsuario: string
{
    /** Cadastra ou altera; reativa se existir desativado. */
    case Cadastrar = 'C';
    /** Cadastra ou altera; não reativa se existir desativado. */
    case Atualizar = 'A';
    /** Exclui (ou desativa, se não for possível excluir). */
    case Excluir = 'E';
    case Desativar = 'D';
    case Reativar = 'R';
}

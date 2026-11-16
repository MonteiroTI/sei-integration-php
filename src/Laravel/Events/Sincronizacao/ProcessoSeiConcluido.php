<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Events\Sincronizacao;

use MonteiroTi\Sei\Dto\Andamento;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;

/** O processo deixou de estar aberto em todas as unidades. */
final class ProcessoSeiConcluido
{
    public function __construct(
        public readonly SeiProcesso $processo,
        public readonly ?Andamento $andamentoConclusao = null,
    ) {}
}

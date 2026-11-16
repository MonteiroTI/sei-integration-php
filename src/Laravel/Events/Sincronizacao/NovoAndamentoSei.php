<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Events\Sincronizacao;

use MonteiroTi\Sei\Dto\Andamento;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;

/**
 * O último andamento do processo mudou desde a sincronização anterior.
 * Andamentos intermediários entre duas sincronizações não são reportados.
 */
final class NovoAndamentoSei
{
    public function __construct(
        public readonly SeiProcesso $processo,
        public readonly Andamento $andamento,
    ) {}
}

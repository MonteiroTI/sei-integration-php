<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Events\Sincronizacao;

use MonteiroTi\Sei\Laravel\Models\SeiProcesso;

/** O processo, que estava concluído, voltou a estar aberto em alguma unidade. */
final class ProcessoSeiReaberto
{
    public function __construct(public readonly SeiProcesso $processo) {}
}

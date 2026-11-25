<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Events\Sincronizacao;

use MonteiroTi\Sei\Dto\Andamento;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;

/**
 * O processo recebeu um andamento desde a sincronização anterior.
 *
 * Sempre há um evento para o último andamento. Se sei.sincronizacao.andamentos.tarefas
 * estiver configurado, há também um evento (intermediario = true) para cada andamento
 * dessas tarefas entre as duas sincronizações, em ordem e antes do último.
 */
final class NovoAndamentoSei
{
    public function __construct(
        public readonly SeiProcesso $processo,
        public readonly Andamento $andamento,
        public readonly bool $intermediario = false,
    ) {}
}

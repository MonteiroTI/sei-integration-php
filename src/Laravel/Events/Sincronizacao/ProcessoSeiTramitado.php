<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Events\Sincronizacao;

use MonteiroTi\Sei\Laravel\Models\SeiProcesso;

/** As unidades onde o processo está aberto mudaram. */
final class ProcessoSeiTramitado
{
    /**
     * @param  list<string>  $antes  IDs das unidades onde estava aberto
     * @param  list<string>  $depois  IDs das unidades onde está aberto
     */
    public function __construct(
        public readonly SeiProcesso $processo,
        public readonly array $antes,
        public readonly array $depois,
    ) {}

    /** @return list<string> */
    public function entrou(): array
    {
        return array_values(array_diff($this->depois, $this->antes));
    }

    /** @return list<string> */
    public function saiu(): array
    {
        return array_values(array_diff($this->antes, $this->depois));
    }
}

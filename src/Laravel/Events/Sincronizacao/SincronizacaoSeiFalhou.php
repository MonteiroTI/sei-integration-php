<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Events\Sincronizacao;

use MonteiroTi\Sei\Core\Exceptions\SeiException;
use MonteiroTi\Sei\Laravel\Models\SeiDocumento;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;

/**
 * O SEI recusou a consulta (processo sigiloso, excluído ou sem acesso para o serviço).
 * A sincronização do registro foi desligada; corrija e reative com sincronizar = true.
 */
final class SincronizacaoSeiFalhou
{
    public function __construct(
        public readonly SeiProcesso|SeiDocumento $registro,
        public readonly SeiException $exception,
    ) {}
}

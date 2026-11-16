<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Events\Sincronizacao;

use MonteiroTi\Sei\Dto\Publicacao;
use MonteiroTi\Sei\Laravel\Models\SeiDocumento;

/** A publicação do documento foi agendada. */
final class PublicacaoSeiAgendada
{
    public function __construct(
        public readonly SeiDocumento $documento,
        public readonly Publicacao $publicacao,
    ) {}
}

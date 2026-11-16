<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Events\Sincronizacao;

use MonteiroTi\Sei\Dto\Publicacao;
use MonteiroTi\Sei\Laravel\Models\SeiDocumento;

/** O documento foi publicado. */
final class DocumentoSeiPublicado
{
    public function __construct(
        public readonly SeiDocumento $documento,
        public readonly Publicacao $publicacao,
    ) {}
}

<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Events\Sincronizacao;

use MonteiroTi\Sei\Dto\Assinatura;
use MonteiroTi\Sei\Laravel\Models\SeiDocumento;

/** O documento recebeu novas assinaturas. */
final class DocumentoSeiAssinado
{
    /**
     * @param  list<Assinatura>  $novas
     */
    public function __construct(
        public readonly SeiDocumento $documento,
        public readonly array $novas,
    ) {}
}

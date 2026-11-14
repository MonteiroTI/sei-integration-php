<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Options;

/**
 * Seções opcionais de consultarDocumento. Cada uma custa processamento extra no SEI.
 */
final readonly class ConsultaDocumentoOptions
{
    public function __construct(
        public bool $andamentoGeracao = false,
        public bool $assinaturas = false,
        public bool $publicacao = false,
        public bool $campos = false,
        public bool $blocos = false,
    ) {}

    public static function completo(): self
    {
        return new self(true, true, true, true, true);
    }
}

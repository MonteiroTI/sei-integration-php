<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;

/** Estrutura RetornoEnvioEmail. */
final readonly class RetornoEnvioEmail implements FromSoap
{
    public function __construct(
        public ?string $idDocumento = null,
        public ?string $documentoFormatado = null,
        public ?string $linkAcesso = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self($data->id('IdDocumento'), $data->string('DocumentoFormatado'), $data->string('LinkAcesso'));
    }
}

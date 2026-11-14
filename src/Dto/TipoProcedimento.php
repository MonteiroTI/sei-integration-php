<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;

/** Estrutura TipoProcedimento (tipo de processo). */
final readonly class TipoProcedimento implements FromSoap
{
    public function __construct(
        public ?string $idTipoProcedimento = null,
        public ?string $nome = null,
        public ?bool $ouvidoriaAnonimo = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self($data->id('IdTipoProcedimento'), $data->string('Nome'), $data->bool('SinOuvidoriaAnonimo'));
    }
}

<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;

/** Estrutura ProcedimentoResumido. */
final readonly class ProcedimentoResumido implements FromSoap
{
    public function __construct(
        public ?string $idProcedimento = null,
        public ?string $procedimentoFormatado = null,
        public ?TipoProcedimento $tipoProcedimento = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self(
            $data->id('IdProcedimento'),
            $data->string('ProcedimentoFormatado'),
            $data->object('TipoProcedimento', TipoProcedimento::class),
        );
    }
}

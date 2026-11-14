<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;

/** Estrutura Estado (UF). */
final readonly class Estado implements FromSoap
{
    public function __construct(
        public ?string $idEstado = null,
        public ?string $idPais = null,
        public ?string $sigla = null,
        public ?string $nome = null,
        public ?string $codigoIbge = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self(
            $data->id('IdEstado'),
            $data->id('IdPais'),
            $data->string('Sigla'),
            $data->string('Nome'),
            $data->string('CodigoIbge'),
        );
    }
}

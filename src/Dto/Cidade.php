<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;

/** Estrutura Cidade. */
final readonly class Cidade implements FromSoap
{
    public function __construct(
        public ?string $idCidade = null,
        public ?string $idEstado = null,
        public ?string $idPais = null,
        public ?string $nome = null,
        public ?string $codigoIbge = null,
        public ?bool $capital = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self(
            $data->id('IdCidade'),
            $data->id('IdEstado'),
            $data->id('IdPais'),
            $data->string('Nome'),
            $data->string('CodigoIbge'),
            $data->bool('SinCapital'),
            $data->float('Latitude'),
            $data->float('Longitude'),
        );
    }
}

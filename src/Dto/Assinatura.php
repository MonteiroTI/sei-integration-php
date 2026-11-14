<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use DateTimeImmutable;
use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;

/** Estrutura Assinatura. */
final readonly class Assinatura implements FromSoap
{
    public function __construct(
        public ?string $nome = null,
        public ?string $cargoFuncao = null,
        public ?DateTimeImmutable $dataHora = null,
        public ?string $idUsuario = null,
        public ?string $idOrigem = null,
        public ?string $idOrgao = null,
        public ?string $sigla = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self(
            $data->string('Nome'),
            $data->string('CargoFuncao'),
            $data->date('DataHora'),
            $data->id('IdUsuario'),
            $data->id('IdOrigem'),
            $data->id('IdOrgao'),
            $data->string('Sigla'),
        );
    }
}

<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;

/** Estrutura Cargo. */
final readonly class Cargo implements FromSoap
{
    public function __construct(
        public ?string $idCargo = null,
        public ?string $expressaoCargo = null,
        public ?string $expressaoTratamento = null,
        public ?string $expressaoVocativo = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self(
            $data->id('IdCargo'),
            $data->string('ExpressaoCargo'),
            $data->string('ExpressaoTratamento'),
            $data->string('ExpressaoVocativo'),
        );
    }
}

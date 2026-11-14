<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;

/** Estrutura Pais. */
final readonly class Pais implements FromSoap
{
    public function __construct(
        public ?string $idPais = null,
        public ?string $nome = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self($data->id('IdPais'), $data->string('Nome'));
    }
}

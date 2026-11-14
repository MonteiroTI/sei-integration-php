<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;

/** Estrutura Usuario. */
final readonly class Usuario implements FromSoap
{
    public function __construct(
        public ?string $idUsuario = null,
        public ?string $sigla = null,
        public ?string $nome = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self($data->id('IdUsuario'), $data->string('Sigla'), $data->string('Nome'));
    }
}

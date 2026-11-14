<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;

/** Estrutura TipoConferencia. */
final readonly class TipoConferencia implements FromSoap
{
    public function __construct(
        public ?string $idTipoConferencia = null,
        public ?string $descricao = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self($data->id('IdTipoConferencia'), $data->string('Descricao'));
    }
}

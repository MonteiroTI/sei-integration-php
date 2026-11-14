<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;

/** Estrutura Marcador. */
final readonly class Marcador implements FromSoap
{
    /**
     * @param  string|null  $icone  PNG codificado em Base64
     */
    public function __construct(
        public ?string $idMarcador = null,
        public ?string $nome = null,
        public ?string $icone = null,
        public ?bool $ativo = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self($data->id('IdMarcador'), $data->string('Nome'), $data->string('Icone'), $data->bool('SinAtivo'));
    }
}

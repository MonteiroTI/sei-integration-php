<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;
use MonteiroTi\Sei\Enum\AplicabilidadeSerie;

/** Estrutura Serie (tipo de documento). */
final readonly class Serie implements FromSoap
{
    public function __construct(
        public ?string $idSerie = null,
        public ?string $nome = null,
        public ?AplicabilidadeSerie $aplicabilidade = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self($data->id('IdSerie'), $data->string('Nome'), $data->enum('Aplicabilidade', AplicabilidadeSerie::class));
    }
}

<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;

/** Estrutura Unidade. */
final readonly class Unidade implements FromSoap
{
    public function __construct(
        public ?string $idUnidade = null,
        public ?string $sigla = null,
        public ?string $descricao = null,
        public ?bool $protocolo = null,
        public ?bool $arquivamento = null,
        public ?bool $ouvidoria = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self(
            $data->id('IdUnidade'),
            $data->string('Sigla'),
            $data->string('Descricao'),
            $data->bool('SinProtocolo'),
            $data->bool('SinArquivamento'),
            $data->bool('SinOuvidoria'),
        );
    }
}

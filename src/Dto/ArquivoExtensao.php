<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;

/** Estrutura ArquivoExtensao. */
final readonly class ArquivoExtensao implements FromSoap
{
    public function __construct(
        public ?string $idArquivoExtensao = null,
        public ?string $extensao = null,
        public ?string $descricao = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self($data->id('IdArquivoExtensao'), $data->string('Extensao'), $data->string('Descricao'));
    }
}

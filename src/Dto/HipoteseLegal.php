<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;
use MonteiroTi\Sei\Enum\NivelAcesso;

/** Estrutura HipoteseLegal. */
final readonly class HipoteseLegal implements FromSoap
{
    public function __construct(
        public ?string $idHipoteseLegal = null,
        public ?string $nome = null,
        public ?string $baseLegal = null,
        public ?NivelAcesso $nivelAcesso = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self(
            $data->id('IdHipoteseLegal'),
            $data->string('Nome'),
            $data->string('BaseLegal'),
            $data->enum('NivelAcesso', NivelAcesso::class),
        );
    }
}

<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;

/** Estrutura TipoPrioridade. */
final readonly class TipoPrioridade implements FromSoap
{
    public function __construct(
        public ?string $idTipoPrioridade = null,
        public ?string $nome = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self($data->id('IdTipoPrioridade'), $data->string('Nome'));
    }
}

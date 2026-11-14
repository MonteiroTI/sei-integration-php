<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use DateTimeImmutable;
use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;

/** Estrutura Feriado. */
final readonly class Feriado implements FromSoap
{
    public function __construct(
        public ?DateTimeImmutable $data = null,
        public ?string $descricao = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self($data->date('Data'), $data->string('Descricao'));
    }
}

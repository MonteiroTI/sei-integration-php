<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;

/** Estrutura Observacao (observação de uma unidade no processo). */
final readonly class Observacao implements FromSoap
{
    public function __construct(
        public ?string $descricao = null,
        public ?Unidade $unidade = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self($data->string('Descricao'), $data->object('Unidade', Unidade::class));
    }
}

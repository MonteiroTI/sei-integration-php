<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;
use MonteiroTi\Sei\Core\Support\ToSoap;

/** Estrutura Assunto (classificação arquivística). */
final readonly class Assunto implements FromSoap, ToSoap
{
    /**
     * @param  string  $codigoEstruturado  Ex.: 00.01.01.01
     */
    public function __construct(
        public string $codigoEstruturado,
        public ?string $descricao = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self($data->requiredString('CodigoEstruturado'), $data->string('Descricao'));
    }

    public function toSoap(): array
    {
        return ['CodigoEstruturado' => $this->codigoEstruturado, 'Descricao' => $this->descricao];
    }
}

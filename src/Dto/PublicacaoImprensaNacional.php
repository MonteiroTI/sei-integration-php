<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use DateTimeInterface;
use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;
use MonteiroTi\Sei\Core\Support\ToSoap;
use MonteiroTi\Sei\Core\Support\Values;

/** Estrutura PublicacaoImprensaNacional. */
final readonly class PublicacaoImprensaNacional implements FromSoap, ToSoap
{
    public function __construct(
        public ?string $idVeiculo = null,
        public ?string $siglaVeiculo = null,
        public ?string $descricaoVeiculo = null,
        public ?string $pagina = null,
        public ?string $idSecao = null,
        public ?string $secao = null,
        public ?DateTimeInterface $data = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self(
            $data->id('IdVeiculo'),
            $data->string('SiglaVeiculo'),
            $data->string('DescricaoVeiculo'),
            $data->string('Pagina'),
            $data->id('IdSecao'),
            $data->string('Secao'),
            $data->date('Data'),
        );
    }

    public function toSoap(): array
    {
        return [
            'IdVeiculo' => $this->idVeiculo,
            'SiglaVeiculo' => $this->siglaVeiculo,
            'DescricaoVeiculo' => $this->descricaoVeiculo,
            'Pagina' => $this->pagina,
            'IdSecao' => $this->idSecao,
            'Secao' => $this->secao,
            'Data' => Values::date($this->data),
        ];
    }
}

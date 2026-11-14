<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;
use MonteiroTi\Sei\Core\Support\ToSoap;

/** Estrutura AtributoAndamento. */
final readonly class AtributoAndamento implements FromSoap, ToSoap
{
    public function __construct(
        public string $nome,
        public ?string $valor = null,
        public ?string $idOrigem = null,
    ) {}

    /** Atributo DESCRICAO usado com a tarefa 65 (atualização de andamento). */
    public static function descricao(string $texto): self
    {
        return new self('DESCRICAO', $texto);
    }

    public static function fromSoap(SoapData $data): static
    {
        return new self($data->requiredString('Nome'), $data->string('Valor'), $data->string('IdOrigem'));
    }

    public function toSoap(): array
    {
        return ['Nome' => $this->nome, 'Valor' => $this->valor, 'IdOrigem' => $this->idOrigem];
    }
}

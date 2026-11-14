<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;
use MonteiroTi\Sei\Core\Support\ToSoap;

/** Estrutura Campo (campo de formulário). */
final readonly class Campo implements FromSoap, ToSoap
{
    public function __construct(
        public string $nome,
        public ?string $valor = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self($data->requiredString('Nome'), $data->string('Valor'));
    }

    public function toSoap(): array
    {
        return ['Nome' => $this->nome, 'Valor' => $this->valor];
    }
}

<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\ToSoap;
use MonteiroTi\Sei\Core\Support\Values;

/** Estrutura Anotacao (registrarAnotacao). */
final readonly class Anotacao implements ToSoap
{
    public function __construct(
        public string $protocoloProcedimento,
        public string $descricao,
        public ?bool $prioridade = null,
    ) {}

    public function toSoap(): array
    {
        return [
            'ProtocoloProcedimento' => $this->protocoloProcedimento,
            'Descricao' => $this->descricao,
            'SinPrioridade' => Values::sn($this->prioridade),
        ];
    }
}

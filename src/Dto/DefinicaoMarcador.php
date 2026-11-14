<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\ToSoap;

/** Estrutura DefinicaoMarcador (definirMarcador). */
final readonly class DefinicaoMarcador implements ToSoap
{
    public function __construct(
        public string $protocoloProcedimento,
        public string $idMarcador,
        public ?string $texto = null,
    ) {}

    public function toSoap(): array
    {
        return [
            'ProtocoloProcedimento' => $this->protocoloProcedimento,
            'IdMarcador' => $this->idMarcador,
            'Texto' => $this->texto,
        ];
    }
}

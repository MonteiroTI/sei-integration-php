<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;

/** Estrutura UnidadeProcedimentoAberto. */
final readonly class UnidadeProcedimentoAberto implements FromSoap
{
    public function __construct(
        public ?Unidade $unidade = null,
        public ?Usuario $usuarioAtribuicao = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self($data->object('Unidade', Unidade::class), $data->object('UsuarioAtribuicao', Usuario::class));
    }
}

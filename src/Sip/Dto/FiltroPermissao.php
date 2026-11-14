<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Sip\Dto;

/** Filtros opcionais de listarPermissao. */
final readonly class FiltroPermissao
{
    public function __construct(
        public ?string $idOrgaoUsuario = null,
        public ?string $idUsuario = null,
        public ?string $idOrigemUsuario = null,
        public ?string $idOrgaoUnidade = null,
        public ?string $idUnidade = null,
        public ?string $idOrigemUnidade = null,
        public ?string $idPerfil = null,
    ) {}
}

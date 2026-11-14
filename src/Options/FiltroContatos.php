<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Options;

/** Filtros de listarContatos. */
final readonly class FiltroContatos
{
    /**
     * @param  list<string>  $idContatos
     */
    public function __construct(
        public ?string $idTipoContato = null,
        public ?string $sigla = null,
        public ?string $nome = null,
        public ?string $cpf = null,
        public ?string $cnpj = null,
        public ?string $matricula = null,
        public array $idContatos = [],
    ) {}
}

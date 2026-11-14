<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\ToSoap;

/** Estrutura AtributoOuvidoria (registrarOuvidoria). */
final readonly class AtributoOuvidoria implements ToSoap
{
    /**
     * @param  string  $id  Identificador do atributo, ex.: P
     * @param  string  $nome  Nome interno, ex.: RELACAO
     * @param  string  $titulo  Título exibido no formulário
     * @param  string|null  $valor  Valor exibido
     */
    public function __construct(
        public string $id,
        public string $nome,
        public string $titulo,
        public ?string $valor = null,
    ) {}

    public function toSoap(): array
    {
        return ['Id' => $this->id, 'Nome' => $this->nome, 'Titulo' => $this->titulo, 'Valor' => $this->valor];
    }
}

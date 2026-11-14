<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use DateTimeImmutable;
use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;

/** Estrutura Andamento (registro do histórico do processo). */
final readonly class Andamento implements FromSoap
{
    /**
     * @param  list<AtributoAndamento>  $atributos
     */
    public function __construct(
        public ?string $idAndamento = null,
        public ?string $idTarefa = null,
        public ?string $idTarefaModulo = null,
        public ?string $descricao = null,
        public ?DateTimeImmutable $dataHora = null,
        public ?Unidade $unidade = null,
        public ?Usuario $usuario = null,
        public array $atributos = [],
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self(
            $data->id('IdAndamento'),
            $data->id('IdTarefa'),
            $data->string('IdTarefaModulo'),
            $data->string('Descricao'),
            $data->date('DataHora'),
            $data->object('Unidade', Unidade::class),
            $data->object('Usuario', Usuario::class),
            $data->list('Atributos', AtributoAndamento::class),
        );
    }

    public function atributo(string $nome): ?string
    {
        foreach ($this->atributos as $atributo) {
            if ($atributo->nome === $nome) {
                return $atributo->valor;
            }
        }

        return null;
    }
}

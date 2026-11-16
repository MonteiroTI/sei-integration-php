<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use DateTimeInterface;
use MonteiroTi\Sei\Core\Support\ToSoap;
use MonteiroTi\Sei\Core\Support\Values;
use MonteiroTi\Sei\Enum\NivelAcesso;

/** Estrutura Procedimento (dados para gerar um processo). */
final readonly class Procedimento implements ToSoap
{
    /**
     * @param  string  $idTipoProcedimento  ID do tipo de processo no SEI
     * @param  list<Assunto>  $assuntos  Somados aos sugeridos para o tipo; obrigatório se o tipo não tiver sugestões
     * @param  list<ContatoRef>  $interessados
     * @param  NivelAcesso|null  $nivelAcesso  Null: usa o nível e a hipótese legal sugeridos para o tipo
     * @param  string|null  $numeroProtocolo  Null: o SEI gera o número
     * @param  DateTimeInterface|null  $dataAutuacao  Null: data atual
     */
    public function __construct(
        public string $idTipoProcedimento,
        public ?string $especificacao = null,
        public array $assuntos = [],
        public array $interessados = [],
        public ?string $observacao = null,
        public ?NivelAcesso $nivelAcesso = null,
        public ?string $idHipoteseLegal = null,
        public ?string $numeroProtocolo = null,
        public ?DateTimeInterface $dataAutuacao = null,
        public ?string $idTipoPrioridade = null,
    ) {}

    /**
     * Cópia com campos alterados, ex.: $p->with(['observacao' => '...']).
     *
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        return new self(...array_replace(get_object_vars($this), $changes));
    }

    public function toSoap(): array
    {
        return [
            'IdTipoProcedimento' => $this->idTipoProcedimento,
            'NumeroProtocolo' => $this->numeroProtocolo,
            'DataAutuacao' => Values::date($this->dataAutuacao),
            'Especificacao' => $this->especificacao,
            'IdTipoPrioridade' => $this->idTipoPrioridade,
            'Assuntos' => Values::list($this->assuntos),
            'Interessados' => Values::list($this->interessados),
            'Observacao' => $this->observacao,
            'NivelAcesso' => $this->nivelAcesso?->value,
            'IdHipoteseLegal' => $this->idHipoteseLegal,
        ];
    }
}

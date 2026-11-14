<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use DateTimeImmutable;
use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;
use MonteiroTi\Sei\Enum\NivelAcesso;

/** Estrutura RetornoConsultaProcedimento. */
final readonly class RetornoConsultaProcedimento implements FromSoap
{
    /**
     * @param  list<UnidadeProcedimentoAberto>  $unidadesProcedimentoAberto
     * @param  list<Assunto>  $assuntos
     * @param  list<ContatoRef>  $interessados
     * @param  list<Observacao>  $observacoes
     * @param  list<ProcedimentoResumido>  $procedimentosRelacionados
     * @param  list<ProcedimentoResumido>  $procedimentosAnexados
     */
    public function __construct(
        public ?string $idProcedimento = null,
        public ?string $procedimentoFormatado = null,
        public ?string $especificacao = null,
        public ?DateTimeImmutable $dataAutuacao = null,
        public ?NivelAcesso $nivelAcessoLocal = null,
        public ?NivelAcesso $nivelAcessoGlobal = null,
        public ?string $linkAcesso = null,
        public ?TipoProcedimento $tipoProcedimento = null,
        public ?TipoPrioridade $tipoPrioridade = null,
        public ?Andamento $andamentoGeracao = null,
        public ?Andamento $andamentoConclusao = null,
        public ?Andamento $ultimoAndamento = null,
        public array $unidadesProcedimentoAberto = [],
        public array $assuntos = [],
        public array $interessados = [],
        public array $observacoes = [],
        public array $procedimentosRelacionados = [],
        public array $procedimentosAnexados = [],
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self(
            $data->id('IdProcedimento'),
            $data->string('ProcedimentoFormatado'),
            $data->string('Especificacao'),
            $data->date('DataAutuacao'),
            $data->enum('NivelAcessoLocal', NivelAcesso::class),
            $data->enum('NivelAcessoGlobal', NivelAcesso::class),
            $data->string('LinkAcesso'),
            $data->object('TipoProcedimento', TipoProcedimento::class),
            $data->object('TipoPrioridade', TipoPrioridade::class),
            $data->object('AndamentoGeracao', Andamento::class),
            $data->object('AndamentoConclusao', Andamento::class),
            $data->object('UltimoAndamento', Andamento::class),
            $data->list('UnidadesProcedimentoAberto', UnidadeProcedimentoAberto::class),
            $data->list('Assuntos', Assunto::class),
            $data->list('Interessados', ContatoRef::class),
            $data->list('Observacoes', Observacao::class),
            $data->list('ProcedimentosRelacionados', ProcedimentoResumido::class),
            $data->list('ProcedimentosAnexados', ProcedimentoResumido::class),
        );
    }

    /** Indica se o processo está aberto em alguma unidade (requer SinRetornarUnidadesProcedimentoAberto). */
    public function aberto(): bool
    {
        return $this->unidadesProcedimentoAberto !== [];
    }
}

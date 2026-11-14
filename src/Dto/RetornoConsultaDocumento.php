<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use DateTimeImmutable;
use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;
use MonteiroTi\Sei\Enum\NivelAcesso;

/** Estrutura RetornoConsultaDocumento. */
final readonly class RetornoConsultaDocumento implements FromSoap
{
    /**
     * @param  list<Assinatura>  $assinaturas
     * @param  list<Campo>  $campos
     * @param  list<Bloco>  $blocos
     */
    public function __construct(
        public ?string $idProcedimento = null,
        public ?string $procedimentoFormatado = null,
        public ?string $idDocumento = null,
        public ?string $documentoFormatado = null,
        public ?NivelAcesso $nivelAcessoLocal = null,
        public ?NivelAcesso $nivelAcessoGlobal = null,
        public ?string $linkAcesso = null,
        public ?Serie $serie = null,
        public ?string $numero = null,
        public ?string $nomeArvore = null,
        public ?string $dinValor = null,
        public ?string $descricao = null,
        public ?DateTimeImmutable $data = null,
        public ?Unidade $unidadeElaboradora = null,
        public ?Andamento $andamentoGeracao = null,
        public array $assinaturas = [],
        public ?Publicacao $publicacao = null,
        public array $campos = [],
        public array $blocos = [],
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self(
            $data->id('IdProcedimento'),
            $data->string('ProcedimentoFormatado'),
            $data->id('IdDocumento'),
            $data->string('DocumentoFormatado'),
            $data->enum('NivelAcessoLocal', NivelAcesso::class),
            $data->enum('NivelAcessoGlobal', NivelAcesso::class),
            $data->string('LinkAcesso'),
            $data->object('Serie', Serie::class),
            $data->string('Numero'),
            $data->string('NomeArvore'),
            $data->string('DinValor'),
            $data->string('Descricao'),
            $data->date('Data'),
            $data->object('UnidadeElaboradora', Unidade::class),
            $data->object('AndamentoGeracao', Andamento::class),
            $data->list('Assinaturas', Assinatura::class),
            $data->object('Publicacao', Publicacao::class),
            $data->list('Campos', Campo::class),
            $data->list('Blocos', Bloco::class),
        );
    }

    public function assinado(): bool
    {
        return $this->assinaturas !== [];
    }
}

<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;

/** Estrutura RetornoGeracaoProcedimento. */
final readonly class RetornoGeracaoProcedimento implements FromSoap
{
    /**
     * @param  list<RetornoInclusaoDocumento>  $documentos  Um item por documento enviado na geração, na mesma ordem
     */
    public function __construct(
        public ?string $idProcedimento = null,
        public ?string $procedimentoFormatado = null,
        public ?string $linkAcesso = null,
        public array $documentos = [],
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self(
            $data->id('IdProcedimento'),
            $data->string('ProcedimentoFormatado'),
            $data->string('LinkAcesso'),
            $data->list('RetornoInclusaoDocumentos', RetornoInclusaoDocumento::class),
        );
    }
}

<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;

/** Estrutura RetornoConsultaPublicacao. */
final readonly class RetornoConsultaPublicacao implements FromSoap
{
    /**
     * @param  list<Assinatura>  $assinaturas
     */
    public function __construct(
        public ?Publicacao $publicacao = null,
        public ?Andamento $andamento = null,
        public array $assinaturas = [],
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self(
            $data->object('Publicacao', Publicacao::class),
            $data->object('Andamento', Andamento::class),
            $data->list('Assinaturas', Assinatura::class),
        );
    }
}

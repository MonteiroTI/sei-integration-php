<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;

/** Estrutura ProtocoloBloco (processo ou documento contido em um bloco). */
final readonly class ProtocoloBloco implements FromSoap
{
    /**
     * @param  list<Assinatura>  $assinaturas
     */
    public function __construct(
        public ?string $protocoloFormatado = null,
        public ?string $identificacao = null,
        public array $assinaturas = [],
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self(
            $data->string('ProtocoloFormatado'),
            $data->string('Identificacao'),
            $data->list('Assinaturas', Assinatura::class),
        );
    }
}

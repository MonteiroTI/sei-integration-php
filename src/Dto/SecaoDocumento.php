<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\Charset;
use MonteiroTi\Sei\Core\Support\ToSoap;

/** Estrutura SecaoDocumento: conteúdo de uma seção do modelo de documento gerado. */
final readonly class SecaoDocumento implements ToSoap
{
    /**
     * @param  string  $nome  Nome da seção no modelo, ex.: "Ementa", "Corpo do Texto"
     * @param  string  $conteudo  HTML da seção, sem Base64 (a biblioteca codifica)
     */
    public function __construct(
        public string $nome,
        public string $conteudo,
    ) {}

    public function toSoap(string $charset = 'UTF-8'): array
    {
        return ['Nome' => $this->nome, 'Conteudo' => base64_encode(Charset::fromUtf8($this->conteudo, $charset))];
    }
}

<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Options;

use MonteiroTi\Sei\Core\Exceptions\InvalidArgumentException;
use MonteiroTi\Sei\Enum\NivelAcesso;

/** Dados de enviarEmail. */
final readonly class Email
{
    /**
     * @param  list<string>  $para
     * @param  list<string>  $cco
     * @param  list<string>  $idDocumentos  IDs internos de documentos do processo a anexar
     * @param  NivelAcesso|null  $nivelAcesso  Apenas Publico (padrão) ou Restrito
     */
    public function __construct(
        public string $de,
        public array $para,
        public string $assunto,
        public string $mensagem,
        public array $cco = [],
        public array $idDocumentos = [],
        public ?NivelAcesso $nivelAcesso = null,
        public ?string $idHipoteseLegal = null,
    ) {
        if ($para === [] && $cco === []) {
            throw new InvalidArgumentException('Informe ao menos um destinatário.');
        }
        if ($nivelAcesso === NivelAcesso::Sigiloso) {
            throw new InvalidArgumentException('E-mail não aceita nível de acesso sigiloso.');
        }
    }
}

<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Options;

use DateTimeInterface;
use MonteiroTi\Sei\Dto\PublicacaoImprensaNacional;
use MonteiroTi\Sei\Enum\MotivoPublicacao;

/** Dados de agendarPublicacao e alterarPublicacao. */
final readonly class DadosPublicacao
{
    public function __construct(
        public MotivoPublicacao $motivo,
        public string $idVeiculoPublicacao,
        public DateTimeInterface $dataDisponibilizacao,
        public ?string $resumo = null,
        public ?PublicacaoImprensaNacional $imprensaNacional = null,
    ) {}
}

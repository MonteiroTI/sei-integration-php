<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Options;

/**
 * Seções opcionais de consultarProcedimento. Cada uma custa processamento extra no SEI:
 * peça apenas o necessário.
 */
final readonly class ConsultaProcedimentoOptions
{
    public function __construct(
        public bool $assuntos = false,
        public bool $interessados = false,
        public bool $observacoes = false,
        public bool $andamentoGeracao = false,
        public bool $andamentoConclusao = false,
        public bool $ultimoAndamento = false,
        public bool $unidadesProcedimentoAberto = false,
        public bool $procedimentosRelacionados = false,
        public bool $procedimentosAnexados = false,
    ) {}

    public static function completo(): self
    {
        return new self(true, true, true, true, true, true, true, true, true);
    }
}

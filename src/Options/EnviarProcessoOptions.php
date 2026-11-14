<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Options;

use DateTimeInterface;
use MonteiroTi\Sei\Core\Exceptions\InvalidArgumentException;

/** Opções de enviarProcesso. */
final readonly class EnviarProcessoOptions
{
    /**
     * @param  bool|null  $reabrir  Reabre se concluído na unidade (exige a operação "Reabrir Processo" liberada)
     */
    public function __construct(
        public ?bool $manterAbertoUnidade = null,
        public ?bool $removerAnotacao = null,
        public ?bool $enviarEmailNotificacao = null,
        public ?DateTimeInterface $dataRetornoProgramado = null,
        public ?int $diasRetornoProgramado = null,
        public ?bool $diasUteisRetornoProgramado = null,
        public ?bool $reabrir = null,
    ) {
        if ($dataRetornoProgramado !== null && $diasRetornoProgramado !== null) {
            throw new InvalidArgumentException('Informe dataRetornoProgramado ou diasRetornoProgramado, não ambos.');
        }
    }
}

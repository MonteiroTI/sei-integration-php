<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Options;

use DateTimeInterface;
use MonteiroTi\Sei\Core\Exceptions\InvalidArgumentException;

/** Opções de gerarProcedimento além do processo e dos documentos. */
final readonly class GerarProcedimentoOptions
{
    /**
     * @param  list<string>  $procedimentosRelacionados  IDs internos de processos a relacionar
     * @param  list<string>  $unidadesEnvio  IDs de unidades para onde enviar após gerar
     * @param  bool|null  $manterAbertoUnidade  Padrão do SEI: S
     */
    public function __construct(
        public array $procedimentosRelacionados = [],
        public array $unidadesEnvio = [],
        public ?bool $manterAbertoUnidade = null,
        public ?bool $enviarEmailNotificacao = null,
        public ?DateTimeInterface $dataRetornoProgramado = null,
        public ?int $diasRetornoProgramado = null,
        public ?bool $diasUteisRetornoProgramado = null,
        public ?string $idMarcador = null,
        public ?string $textoMarcador = null,
        public ?DateTimeInterface $dataControlePrazo = null,
        public ?int $diasControlePrazo = null,
        public ?bool $diasUteisControlePrazo = null,
    ) {
        if ($dataRetornoProgramado !== null && $diasRetornoProgramado !== null) {
            throw new InvalidArgumentException('Informe dataRetornoProgramado ou diasRetornoProgramado, não ambos.');
        }
        if ($dataControlePrazo !== null && $diasControlePrazo !== null) {
            throw new InvalidArgumentException('Informe dataControlePrazo ou diasControlePrazo, não ambos.');
        }
    }
}

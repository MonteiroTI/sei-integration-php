<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use DateTimeImmutable;
use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;

/** Estrutura AndamentoMarcador. */
final readonly class AndamentoMarcador implements FromSoap
{
    /**
     * @param  Marcador|null  $marcador  Nulo em andamentos de remoção de marcador
     */
    public function __construct(
        public ?string $idAndamentoMarcador = null,
        public ?string $texto = null,
        public ?DateTimeImmutable $dataHora = null,
        public ?Usuario $usuario = null,
        public ?Marcador $marcador = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self(
            $data->id('IdAndamentoMarcador'),
            $data->string('Texto'),
            $data->date('DataHora'),
            $data->object('Usuario', Usuario::class),
            $data->object('Marcador', Marcador::class),
        );
    }
}

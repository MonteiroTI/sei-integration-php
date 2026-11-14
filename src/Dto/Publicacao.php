<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use DateTimeImmutable;
use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;
use MonteiroTi\Sei\Enum\EstadoPublicacao;
use MonteiroTi\Sei\Enum\MotivoPublicacao;
use MonteiroTi\Sei\Enum\TipoVeiculo;

/** Estrutura Publicacao. */
final readonly class Publicacao implements FromSoap
{
    public function __construct(
        public ?string $idPublicacao = null,
        public ?string $idDocumento = null,
        public ?MotivoPublicacao $motivo = null,
        public ?string $resumo = null,
        public ?string $idVeiculoPublicacao = null,
        public ?string $nomeVeiculo = null,
        public ?TipoVeiculo $tipoVeiculo = null,
        public ?string $numero = null,
        public ?DateTimeImmutable $dataDisponibilizacao = null,
        public ?DateTimeImmutable $dataPublicacao = null,
        public ?EstadoPublicacao $estado = null,
        public ?PublicacaoImprensaNacional $imprensaNacional = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self(
            $data->id('IdPublicacao'),
            $data->id('IdDocumento'),
            $data->enum('StaMotivo', MotivoPublicacao::class),
            $data->string('Resumo'),
            $data->id('IdVeiculoPublicacao'),
            $data->string('NomeVeiculo'),
            $data->enum('StaTipoVeiculo', TipoVeiculo::class),
            $data->string('Numero'),
            $data->date('DataDisponibilizacao'),
            $data->date('DataPublicacao'),
            $data->enum('Estado', EstadoPublicacao::class),
            $data->object('ImprensaNacional', PublicacaoImprensaNacional::class),
        );
    }
}

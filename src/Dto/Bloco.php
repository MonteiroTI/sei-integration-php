<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;
use MonteiroTi\Sei\Enum\EstadoBloco;
use MonteiroTi\Sei\Enum\TipoBloco;

/**
 * Estruturas Bloco e RetornoConsultaBloco (esta última com os protocolos).
 */
final readonly class Bloco implements FromSoap
{
    /**
     * @param  list<Unidade>  $unidadesDisponibilizacao
     * @param  list<ProtocoloBloco>  $protocolos  Preenchido apenas em consultarBloco com retorno de protocolos
     */
    public function __construct(
        public ?string $idBloco = null,
        public ?Unidade $unidade = null,
        public ?Usuario $usuario = null,
        public ?string $descricao = null,
        public ?TipoBloco $tipo = null,
        public ?EstadoBloco $estado = null,
        public ?bool $prioridade = null,
        public ?bool $revisao = null,
        public ?Usuario $usuarioAtribuicao = null,
        public array $unidadesDisponibilizacao = [],
        public array $protocolos = [],
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self(
            $data->id('IdBloco'),
            $data->object('Unidade', Unidade::class),
            $data->object('Usuario', Usuario::class),
            $data->string('Descricao'),
            $data->enum('Tipo', TipoBloco::class),
            $data->enum('Estado', EstadoBloco::class),
            $data->bool('SinPrioridade'),
            $data->bool('SinRevisao'),
            $data->object('UsuarioAtribuicao', Usuario::class),
            $data->list('UnidadesDisponibilizacao', Unidade::class),
            $data->list('Protocolos', ProtocoloBloco::class),
        );
    }
}

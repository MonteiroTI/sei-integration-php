<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Sip\Dto;

use DateTimeInterface;
use MonteiroTi\Sei\Core\Exceptions\InvalidArgumentException;
use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;
use MonteiroTi\Sei\Core\Support\ToSoap;
use MonteiroTi\Sei\Core\Support\Values;
use MonteiroTi\Sei\Sip\Enum\OperacaoPermissao;

/**
 * Estrutura Permissao do SIP (listarPermissao e replicarPermissao).
 *
 * Usuário: IdUsuario ou IdOrigemUsuario. Unidade: IdUnidade ou IdOrigemUnidade.
 * Se só IdOrigemUnidade for informado e houver várias unidades com esse valor, a operação vale para todas.
 */
final readonly class Permissao implements FromSoap, ToSoap
{
    public function __construct(
        public ?OperacaoPermissao $operacao = null,
        public ?string $idSistema = null,
        public ?string $idOrgaoUsuario = null,
        public ?string $idUsuario = null,
        public ?string $idOrigemUsuario = null,
        public ?string $idOrgaoUnidade = null,
        public ?string $idUnidade = null,
        public ?string $idOrigemUnidade = null,
        public ?string $idPerfil = null,
        public ?DateTimeInterface $dataInicial = null,
        public ?DateTimeInterface $dataFinal = null,
        public ?bool $subunidades = null,
    ) {}

    public static function fromSoap(SoapData $data): static
    {
        return new self(
            $data->enum('StaOperacao', OperacaoPermissao::class),
            $data->id('IdSistema'),
            $data->id('IdOrgaoUsuario'),
            $data->id('IdUsuario'),
            $data->string('IdOrigemUsuario'),
            $data->id('IdOrgaoUnidade'),
            $data->id('IdUnidade'),
            $data->string('IdOrigemUnidade'),
            $data->id('IdPerfil'),
            $data->date('DataInicial'),
            $data->date('DataFinal'),
            $data->bool('SinSubunidades'),
        );
    }

    public function toSoap(): array
    {
        if ($this->operacao === null) {
            throw new InvalidArgumentException('Informe a operação (StaOperacao) da permissão.');
        }
        if ($this->idUsuario === null && $this->idOrigemUsuario === null) {
            throw new InvalidArgumentException('Informe idUsuario ou idOrigemUsuario.');
        }
        if ($this->idUnidade === null && $this->idOrigemUnidade === null) {
            throw new InvalidArgumentException('Informe idUnidade ou idOrigemUnidade.');
        }
        if ($this->operacao === OperacaoPermissao::Atualizar && $this->dataInicial === null) {
            throw new InvalidArgumentException('dataInicial é obrigatória ao atualizar uma permissão.');
        }

        return [
            'StaOperacao' => $this->operacao->value,
            'IdSistema' => $this->idSistema,
            'IdOrgaoUsuario' => $this->idOrgaoUsuario,
            'IdUsuario' => $this->idUsuario,
            'IdOrigemUsuario' => $this->idOrigemUsuario,
            'IdOrgaoUnidade' => $this->idOrgaoUnidade,
            'IdUnidade' => $this->idUnidade,
            'IdOrigemUnidade' => $this->idOrigemUnidade,
            'IdPerfil' => $this->idPerfil,
            'DataInicial' => Values::date($this->dataInicial),
            'DataFinal' => Values::date($this->dataFinal),
            'SinSubunidades' => Values::sn($this->subunidades),
        ];
    }
}

<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use DateTimeInterface;
use MonteiroTi\Sei\Core\Exceptions\InvalidArgumentException;
use MonteiroTi\Sei\Core\Support\ToSoap;
use MonteiroTi\Sei\Core\Support\Values;

/** Estrutura DefinicaoControlePrazo: prazo por data OU por número de dias. */
final readonly class DefinicaoControlePrazo implements ToSoap
{
    public function __construct(
        public string $protocoloProcedimento,
        public ?DateTimeInterface $dataPrazo = null,
        public ?int $dias = null,
        public ?bool $diasUteis = null,
    ) {
        if (($dataPrazo === null) === ($dias === null)) {
            throw new InvalidArgumentException('Informe dataPrazo ou dias (exatamente um deles).');
        }
        if ($dataPrazo !== null && $diasUteis !== null) {
            throw new InvalidArgumentException('diasUteis só se aplica quando o prazo é informado em dias.');
        }
    }

    public static function ate(string $protocoloProcedimento, DateTimeInterface $data): self
    {
        return new self($protocoloProcedimento, dataPrazo: $data);
    }

    public static function emDias(string $protocoloProcedimento, int $dias, bool $uteis = false): self
    {
        return new self($protocoloProcedimento, dias: $dias, diasUteis: $uteis);
    }

    public function toSoap(): array
    {
        return [
            'ProtocoloProcedimento' => $this->protocoloProcedimento,
            'DataPrazo' => Values::date($this->dataPrazo),
            'Dias' => $this->dias,
            'SinDiasUteis' => Values::sn($this->diasUteis),
        ];
    }
}

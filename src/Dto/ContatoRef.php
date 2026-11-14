<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use MonteiroTi\Sei\Core\Exceptions\InvalidArgumentException;
use MonteiroTi\Sei\Core\Support\FromSoap;
use MonteiroTi\Sei\Core\Support\SoapData;
use MonteiroTi\Sei\Core\Support\ToSoap;

/**
 * Referência a um contato: estruturas Interessado, Remetente e Destinatario.
 *
 * O SEI procura o contato nesta ordem: IdContato, Cpf, Cnpj, Sigla+Nome, Sigla ou Nome.
 * Se não encontrar, cadastra um novo contato — por isso informe o Nome sempre que possível.
 */
final readonly class ContatoRef implements FromSoap, ToSoap
{
    public function __construct(
        public ?string $sigla = null,
        public ?string $nome = null,
        public ?string $idContato = null,
        public ?string $cpf = null,
        public ?string $cnpj = null,
    ) {}

    public static function id(string $idContato): self
    {
        return new self(idContato: $idContato);
    }

    public static function cpf(string $cpf, ?string $nome = null, ?string $sigla = null): self
    {
        return new self(sigla: $sigla, nome: $nome, cpf: $cpf);
    }

    public static function cnpj(string $cnpj, ?string $nome = null, ?string $sigla = null): self
    {
        return new self(sigla: $sigla, nome: $nome, cnpj: $cnpj);
    }

    public static function nome(string $nome, ?string $sigla = null): self
    {
        return new self(sigla: $sigla, nome: $nome);
    }

    public static function fromSoap(SoapData $data): static
    {
        return new self(
            $data->string('Sigla'),
            $data->string('Nome'),
            $data->id('IdContato'),
            $data->string('Cpf'),
            $data->string('Cnpj'),
        );
    }

    public function toSoap(): array
    {
        if ($this->sigla === null && $this->nome === null && $this->idContato === null && $this->cpf === null && $this->cnpj === null) {
            throw new InvalidArgumentException('Informe ao menos um identificador do contato (IdContato, Cpf, Cnpj, Sigla ou Nome).');
        }

        return [
            'Sigla' => $this->sigla,
            'Nome' => $this->nome,
            'IdContato' => $this->idContato,
            'Cpf' => $this->cpf,
            'Cnpj' => $this->cnpj,
        ];
    }
}

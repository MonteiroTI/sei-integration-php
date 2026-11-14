<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Sip\Dto;

use MonteiroTi\Sei\Core\Support\ToSoap;
use MonteiroTi\Sei\Sip\Enum\OperacaoUsuario;

/**
 * Estrutura Usuario do SIP (replicarUsuario).
 *
 * Em Excluir, Desativar e Reativar só IdOrigem é considerado (e IdOrgao, se SinConsiderarOrgao = S).
 */
final readonly class Usuario implements ToSoap
{
    /**
     * @param  string  $idOrigem  ID do usuário no sistema de origem
     * @param  string|null  $nome  Nome de registro civil
     * @param  string|null  $email  E-mail institucional
     */
    public function __construct(
        public OperacaoUsuario $operacao,
        public string $idOrigem,
        public ?string $idOrgao = null,
        public ?string $sigla = null,
        public ?string $nome = null,
        public ?string $nomeSocial = null,
        public ?string $cpf = null,
        public ?string $email = null,
    ) {}

    public function toSoap(): array
    {
        return [
            'StaOperacao' => $this->operacao->value,
            'IdOrigem' => $this->idOrigem,
            'IdOrgao' => $this->idOrgao,
            'Sigla' => $this->sigla,
            'Nome' => $this->nome,
            'NomeSocial' => $this->nomeSocial,
            'Cpf' => $this->cpf,
            'Email' => $this->email,
        ];
    }
}

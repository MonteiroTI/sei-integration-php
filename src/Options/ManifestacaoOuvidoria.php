<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Options;

use MonteiroTi\Sei\Core\Exceptions\InvalidArgumentException;
use MonteiroTi\Sei\Dto\Anexo;
use MonteiroTi\Sei\Dto\AtributoOuvidoria;

/** Dados de registrarOuvidoria. */
final readonly class ManifestacaoOuvidoria
{
    /**
     * @param  string  $estado  UF do cidadão
     * @param  string|null  $processos  Relação textual de processos citados pelo cidadão
     * @param  list<Anexo>  $anexos
     * @param  list<AtributoOuvidoria>  $atributosAdicionais
     */
    public function __construct(
        public string $idOrgao,
        public string $idTipoProcedimento,
        public string $nome,
        public string $email,
        public string $mensagem,
        public ?string $cpf = null,
        public ?string $rg = null,
        public ?string $orgaoExpedidor = null,
        public ?string $nomeSocial = null,
        public ?string $telefone = null,
        public ?string $estado = null,
        public ?string $cidade = null,
        public ?string $processos = null,
        public bool $retorno = false,
        public bool $anonimo = false,
        public bool $sigilo = false,
        public array $anexos = [],
        public array $atributosAdicionais = [],
    ) {
        if (! $anonimo && $cpf === null && ($rg === null || $orgaoExpedidor === null)) {
            throw new InvalidArgumentException('Informe o CPF ou o RG com o órgão expedidor.');
        }
    }
}

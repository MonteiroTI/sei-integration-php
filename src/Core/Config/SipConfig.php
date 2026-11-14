<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Config;

/**
 * Credenciais de uma conexão com o WebService do SIP.
 */
final readonly class SipConfig
{
    /**
     * @param  string  $chaveAcesso  Chave de acesso gerada no cadastro do sistema no SIP
     * @param  string|null  $idSistema  ID do sistema no SIP, usado como padrão em listarPermissao
     */
    public function __construct(
        public string $chaveAcesso,
        public ?string $idSistema = null,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        if (empty($config['chave_acesso'])) {
            throw new \InvalidArgumentException('A opção "chave_acesso" é obrigatória.');
        }

        return new self(
            chaveAcesso: (string) $config['chave_acesso'],
            idSistema: isset($config['id_sistema']) && $config['id_sistema'] !== '' ? (string) $config['id_sistema'] : null,
        );
    }
}

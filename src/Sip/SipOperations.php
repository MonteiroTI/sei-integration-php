<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Sip;

/**
 * Operações do WebService do SIP (controlador_ws.php?servico=sip) e ordem dos parâmetros.
 */
final class SipOperations
{
    /** @var array<string, list<string>> */
    private const PARAMETERS = [
        'listarPermissao' => ['ChaveAcesso', 'IdSistema', 'IdOrgaoUsuario', 'IdUsuario', 'IdOrigemUsuario', 'IdOrgaoUnidade', 'IdUnidade', 'IdOrigemUnidade', 'IdPerfil'],
        'replicarPermissao' => ['ChaveAcesso', 'Permissoes'],
        'replicarUsuario' => ['ChaveAcesso', 'Usuarios', 'SinConsiderarOrgao'],
    ];

    /**
     * @return list<string>
     */
    public static function parameters(string $operation): array
    {
        return self::PARAMETERS[$operation] ?? throw new \InvalidArgumentException(sprintf('Operação SIP desconhecida: "%s".', $operation));
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::PARAMETERS);
    }
}

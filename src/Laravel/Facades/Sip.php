<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use MonteiroTi\Sei\Laravel\SipManager;

/**
 * @method static \MonteiroTi\Sei\Sip\SipClient connection(?string $name = null)
 * @method static list<\MonteiroTi\Sei\Sip\Dto\Permissao> listarPermissao(?\MonteiroTi\Sei\Sip\Dto\FiltroPermissao $filtro = null, ?string $idSistema = null)
 * @method static void replicarPermissao(\MonteiroTi\Sei\Sip\Dto\Permissao ...$permissoes)
 * @method static void replicarUsuario(list<\MonteiroTi\Sei\Sip\Dto\Usuario> $usuarios, bool $considerarOrgao = false)
 *
 * @see SipManager
 */
class Sip extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SipManager::class;
    }
}

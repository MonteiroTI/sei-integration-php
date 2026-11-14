<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTi\Sei\Laravel\Testing\SeiFake;

/**
 * @method static \MonteiroTi\Sei\SeiClient connection(?string $name = null)
 * @method static \MonteiroTi\Sei\Sip\SipClient sip(?string $name = null)
 * @method static \MonteiroTi\Sei\SeiClient naUnidade(string $idUnidade)
 * @method static \MonteiroTi\Sei\Api\Processos processos()
 * @method static \MonteiroTi\Sei\Api\Andamentos andamentos()
 * @method static \MonteiroTi\Sei\Api\Documentos documentos()
 * @method static \MonteiroTi\Sei\Api\Blocos blocos()
 * @method static \MonteiroTi\Sei\Api\Publicacoes publicacoes()
 * @method static \MonteiroTi\Sei\Api\Contatos contatos()
 * @method static \MonteiroTi\Sei\Api\Ouvidoria ouvidoria()
 * @method static \MonteiroTi\Sei\Api\Catalogos catalogos(bool $cache = true)
 * @method static mixed call(string $operation, array<string, mixed> $parameters = [])
 * @method static void purge(?string $name = null)
 *
 * @see SeiManager
 */
class Sei extends Facade
{
    public static function fake(): SeiFake
    {
        /** @var SeiManager $manager */
        $manager = static::getFacadeRoot();

        return $manager->fake();
    }

    protected static function getFacadeAccessor(): string
    {
        return SeiManager::class;
    }
}

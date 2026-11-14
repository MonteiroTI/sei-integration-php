<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Tests\Laravel;

use MonteiroTi\Sei\Laravel\SeiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [SeiServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('sei.connections.default', [
            'wsdl' => dirname(__DIR__).'/Fixtures/sei-mini.wsdl',
            'sigla_sistema' => 'SISTEMA',
            'identificacao_servico' => 'chave-secreta',
            'id_unidade' => '110000001',
            'wsdl_cache' => WSDL_CACHE_NONE,
        ]);
        $app['config']->set('sei.connections.orgao-b', [
            'wsdl' => 'https://sei.orgao-b.test/sei/controlador_ws.php?servico=sei',
            'sigla_sistema' => 'OUTRO',
            'identificacao_servico' => 'outra-chave',
            'id_unidade' => '220000002',
        ]);
        $app['config']->set('sei.sip.connections.default', [
            'wsdl' => 'https://sip.orgao.test/sip/controlador_ws.php?servico=sip',
            'chave_acesso' => 'chave-sip',
            'id_sistema' => '100000100',
        ]);
        $app['config']->set('cache.default', 'array');
    }
}

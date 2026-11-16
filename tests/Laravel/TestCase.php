<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Tests\Laravel;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
        $app['config']->set('database.default', 'testing');
        $app['config']->set('sei.connections.default.parametros', [
            'unidades' => ['protocolo' => '110000001'],
            'tipos_processo' => ['ferias' => '100000368', 'inexistente' => '999'],
            'outros' => ['qualquer' => '1'],
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        (require dirname(__DIR__, 2).'/database/migrations/create_sei_tables.php.stub')->up();

        Schema::create('requerimentos', function (Blueprint $table) {
            $table->id();
            $table->string('assunto')->nullable();
            $table->timestamps();
        });
    }
}

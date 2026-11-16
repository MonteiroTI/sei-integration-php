<?php

declare(strict_types=1);

use MonteiroTi\Sei\Core\Config\SeiConfig;
use MonteiroTi\Sei\Core\Transport\FakeTransport;
use MonteiroTi\Sei\SeiClient;
use MonteiroTi\Sei\Tests\Laravel\TestCase;

uses(TestCase::class)->in('Laravel');

// Variáveis da homologação (SEI_TEST_*, SIP_TEST_*), fora do controle de versão.
if (is_file(dirname(__DIR__).'/.env.homologacao')) {
    Dotenv\Dotenv::createUnsafeImmutable(dirname(__DIR__), '.env.homologacao')->safeLoad();
}

function fakeSei(?FakeTransport &$transport = null, ?string $idUnidade = '110000001', string $charset = 'UTF-8', int $chunk = 5 * 1024 * 1024): SeiClient
{
    $transport = new FakeTransport;

    return new SeiClient($transport, new SeiConfig('SISTEMA', 'chave-secreta', $idUnidade, $charset, $chunk));
}

function seiFixture(string $name): string
{
    return __DIR__.'/Fixtures/'.$name;
}

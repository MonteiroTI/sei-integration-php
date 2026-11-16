<?php

declare(strict_types=1);

/*
 * Baixa as WSDLs de homologação para tests/Fixtures, usando .env.homologacao.
 * Uso: composer wsdl:baixar
 */

use MonteiroTi\Sei\Core\Config\TransportOptions;
use MonteiroTi\Sei\Core\Transport\SoapTransport;

require dirname(__DIR__).'/vendor/autoload.php';

$raiz = dirname(__DIR__);
if (! is_file($raiz.'/.env.homologacao')) {
    fwrite(STDERR, "Crie .env.homologacao a partir de .env.homologacao.example.\n");
    exit(1);
}
Dotenv\Dotenv::createUnsafeImmutable($raiz, '.env.homologacao')->safeLoad();

$alvos = ['SEI_TEST_WSDL' => 'sei-5.0.wsdl', 'SIP_TEST_WSDL' => 'sip.wsdl'];
$falhas = 0;

foreach ($alvos as $variavel => $arquivo) {
    $url = getenv($variavel);
    if (! is_string($url) || $url === '') {
        echo "{$variavel} não definida: {$arquivo} ignorada.\n";

        continue;
    }

    try {
        $wsdl = (new SoapTransport(new TransportOptions($url, verifySsl: getenv('SEI_TEST_VERIFY_SSL') !== '0')))->downloadWsdl();
        file_put_contents($raiz.'/tests/Fixtures/'.$arquivo, $wsdl);
        printf("%s salva (%d bytes).\n", $arquivo, strlen($wsdl));
    } catch (Throwable $e) {
        fwrite(STDERR, "{$arquivo}: {$e->getMessage()}\n");
        $falhas++;
    }
}

exit($falhas > 0 ? 1 : 0);

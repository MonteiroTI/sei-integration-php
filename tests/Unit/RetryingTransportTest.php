<?php

declare(strict_types=1);

use MonteiroTi\Sei\Core\Config\RetryOptions;
use MonteiroTi\Sei\Core\Config\SeiConfig;
use MonteiroTi\Sei\Core\Exceptions\ConnectionException;
use MonteiroTi\Sei\Core\Exceptions\FaultException;
use MonteiroTi\Sei\Core\Transport\FakeTransport;
use MonteiroTi\Sei\Core\Transport\RetryingTransport;
use MonteiroTi\Sei\Core\Transport\SoapTransport;
use MonteiroTi\Sei\SeiClient;
use MonteiroTi\Sei\Sip\SipClient;

/**
 * @param  list<int>  $esperas
 */
function retrying(FakeTransport $fake, array &$esperas, RetryOptions $options = new RetryOptions(jitter: false)): RetryingTransport
{
    return new RetryingTransport($fake, $options, function (int $ms) use (&$esperas): void {
        $esperas[] = $ms;
    });
}

it('repete leituras após falha de conexão e devolve o sucesso', function () {
    $fake = (new FakeTransport)->respond(
        'listarUnidades',
        new ConnectionException('Error Fetching http headers', 'listarUnidades', possivelmenteEnviada: true),
        new ConnectionException('Could not connect to host', 'listarUnidades'),
        [['IdUnidade' => '1']],
    );
    $esperas = [];

    $resultado = retrying($fake, $esperas)->call('listarUnidades', []);

    expect($resultado)->toBe([['IdUnidade' => '1']])
        ->and($fake->calls('listarUnidades'))->toHaveCount(3)
        ->and($esperas)->toBe([200, 400]);
});

it('desiste depois do número de tentativas e relança a última exceção', function () {
    $ultima = new ConnectionException('terceira', 'consultarProcedimento');
    $fake = (new FakeTransport)->respond(
        'consultarProcedimento',
        new ConnectionException('primeira', 'consultarProcedimento'),
        new ConnectionException('segunda', 'consultarProcedimento'),
        $ultima,
    );
    $esperas = [];

    expect(fn () => retrying($fake, $esperas)->call('consultarProcedimento', []))
        ->toThrow(fn (ConnectionException $e) => expect($e)->toBe($ultima));
    expect($fake->calls())->toHaveCount(3);
});

it('não repete escritas', function () {
    $fake = (new FakeTransport)->respond('gerarProcedimento', new ConnectionException('Could not connect to host', 'gerarProcedimento'));
    $esperas = [];

    expect(fn () => retrying($fake, $esperas)->call('gerarProcedimento', []))->toThrow(ConnectionException::class);
    expect($fake->calls())->toHaveCount(1)->and($esperas)->toBe([]);
});

it('não repete erros de negócio', function () {
    $fake = (new FakeTransport)->respond('consultarDocumento', new FaultException('Documento não encontrado.', 'consultarDocumento'));
    $esperas = [];

    expect(fn () => retrying($fake, $esperas)->call('consultarDocumento', []))->toThrow(FaultException::class);
    expect($fake->calls())->toHaveCount(1);
});

it('limita o backoff à espera máxima e aplica jitter entre a metade e o valor', function () {
    $options = new RetryOptions(tentativas: 5, esperaMs: 300, multiplicador: 3, esperaMaxMs: 1000, jitter: false);
    expect(array_map($options->espera(...), [1, 2, 3, 4]))->toBe([300, 900, 1000, 1000]);

    $fake = (new FakeTransport)->respond('listarSeries', new ConnectionException('x', 'listarSeries'));
    $esperas = [];
    $comJitter = new RetryOptions(tentativas: 3, esperaMs: 400, multiplicador: 1, esperaMaxMs: 400);

    $transport = retrying($fake, $esperas, $comJitter);

    expect(fn () => $transport->call('listarSeries', []))->toThrow(ConnectionException::class);
    expect($esperas)->toHaveCount(3)->each->toBeGreaterThanOrEqual(200)->toBeLessThanOrEqual(400);
});

it('avisa antes de cada nova tentativa', function () {
    $fake = (new FakeTransport)->respond('listarPermissao', new ConnectionException('x', 'listarPermissao'), []);
    $avisos = [];
    $transport = new RetryingTransport($fake, new RetryOptions(jitter: false), fn () => null,
        function (string $op, int $n, ConnectionException $e, int $ms) use (&$avisos): void {
            $avisos[] = [$op, $n, $ms];
        });

    $transport->call('listarPermissao', []);

    expect($avisos)->toBe([['listarPermissao', 1, 200]]);
});

it('lê a opção retry da configuração', function () {
    expect(RetryOptions::fromConfig(null))->toEqual(new RetryOptions)
        ->and(RetryOptions::fromConfig(false))->toBeNull()
        ->and(RetryOptions::fromConfig(['tentativas' => 0]))->toBeNull()
        ->and(RetryOptions::fromConfig(['tentativas' => 4, 'espera_ms' => 50])?->tentativas)->toBe(4)
        ->and(fn () => RetryOptions::fromConfig(['multiplicador' => 0.5]))->toThrow(InvalidArgumentException::class);
});

it('create() monta o retry, que pode ser desligado', function () {
    $config = ['wsdl' => seiFixture('sei-mini.wsdl'), 'sigla_sistema' => 'S', 'identificacao_servico' => 'k', 'chave_acesso' => 'k'];

    expect(SeiClient::create($config)->transport())->toBeInstanceOf(RetryingTransport::class)
        ->and(SeiClient::create($config + ['retry' => false])->transport())->toBeInstanceOf(SoapTransport::class)
        ->and(SipClient::create($config)->transport())->toBeInstanceOf(RetryingTransport::class);
});

it('SeiConfig::leitura reconhece consultar* e listar*', function () {
    expect(SeiConfig::leitura('consultarProcedimento'))->toBeTrue()
        ->and(SeiConfig::leitura('listarPermissao'))->toBeTrue()
        ->and(SeiConfig::leitura('gerarProcedimento'))->toBeFalse()
        ->and(SeiConfig::casa('adicionarArquivo', ['adicionar*']))->toBeTrue();
});

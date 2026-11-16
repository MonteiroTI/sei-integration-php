<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use MonteiroTi\Sei\Core\Exceptions\ValidationException;
use MonteiroTi\Sei\Core\Transport\FakeTransport;
use MonteiroTi\Sei\Dto\Contato;
use MonteiroTi\Sei\Laravel\Events\SeiRequestSending;
use MonteiroTi\Sei\Laravel\Events\SeiResponseReceived;
use MonteiroTi\Sei\Laravel\Facades\Sei;
use MonteiroTi\Sei\Laravel\Facades\Sip;
use MonteiroTi\Sei\Laravel\LaravelCatalogCache;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTi\Sei\SeiClient;
use MonteiroTi\Sei\Sip\SipClient;
use Psr\Log\LoggerInterface;

it('resolve conexões nomeadas e injeta a padrão', function () {
    $manager = app(SeiManager::class);

    expect($manager->connection()->config()->siglaSistema)->toBe('SISTEMA')
        ->and($manager->connection('orgao-b')->config()->idUnidade)->toBe('220000002')
        ->and($manager->connection('orgao-b'))->toBe($manager->connection('orgao-b'))
        ->and(app(SeiClient::class)->config()->siglaSistema)->toBe('SISTEMA')
        ->and(app(SipClient::class)->config()->idSistema)->toBe('100000100')
        ->and(fn () => $manager->connection('nao-existe'))->toThrow(InvalidArgumentException::class);
});

it('permite fake com asserções pela facade', function () {
    $fake = Sei::fake()->respond('concluirProcesso', true);

    Sei::processos()->concluir('12.1.000000077-4');
    Sei::connection('orgao-b')->processos()->concluir('P2');

    $fake->assertCalledTimes('concluirProcesso', 2)
        ->assertCalled('concluirProcesso', fn (array $args) => $args['IdUnidade'] === '220000002' && $args['ProtocoloProcedimento'] === 'P2')
        ->assertNotCalled('gerarProcedimento');
});

it('o fake também cobre o SIP', function () {
    $fake = Sei::fake()->respond('replicarUsuario', true);

    Sip::replicarUsuario([]);

    $fake->assertCalled('replicarUsuario', fn (array $args) => $args['ChaveAcesso'] === 'chave-sip');
});

it('mascara dados pessoais e mensagens de erro nos logs', function () {
    config(['sei.log.enabled' => true]);
    $logger = Mockery::spy(LoggerInterface::class);
    Log::shouldReceive('channel')->andReturn($logger);
    Sei::fake()->respond('atualizarContatos', new ValidationException('CPF 052.559.530-91 inválido.', 'atualizarContatos'));

    expect(fn () => Sei::contatos()->atualizar(new Contato(nome: 'Paulo Silva', cpf: '052.559.530-91')))
        ->toThrow(ValidationException::class);

    $logger->shouldHaveReceived('warning')->withArgs(fn (string $msg, array $ctx) => $ctx['message'] === 'CPF ***.***.***-91 inválido.'
        && $ctx['arguments']['Contatos'][0]['Cpf'] === '***.***.***-91'
        && $ctx['arguments']['Contatos'][0]['Nome'] === 'P. S.');
});

it('dispara eventos e registra logs com a chave mascarada', function () {
    config(['sei.log.enabled' => true]);
    Event::fake([SeiRequestSending::class, SeiResponseReceived::class]);
    $logger = Mockery::spy(LoggerInterface::class);
    Log::shouldReceive('channel')->andReturn($logger);
    Sei::fake()->respond('reabrirProcesso', true);

    Sei::processos()->reabrir('P');

    Event::assertDispatched(SeiRequestSending::class, fn (SeiRequestSending $e) => $e->operation === 'reabrirProcesso'
        && $e->arguments['IdentificacaoServico'] === '***'
        && $e->connection === 'sei:default');
    Event::assertDispatched(SeiResponseReceived::class);
    $logger->shouldHaveReceived('info')->withArgs(fn (string $msg, array $ctx) => $msg === 'SEI reabrirProcesso'
        && $ctx['arguments']['IdentificacaoServico'] === '***');
});

it('guarda catálogos em cache por conexão e unidade', function () {
    $manager = app(SeiManager::class);
    $transport = new FakeTransport;
    $transport->respond('listarSeries', [(object) ['IdSerie' => '3', 'Nome' => 'Portaria']]);
    $client = new SeiClient($transport, $manager->connection()->config(), new LaravelCatalogCache(cache()->store(), 60));

    $client->catalogos()->series();
    $series = $client->catalogos()->series();
    $client->catalogos(cache: false)->series();
    $client->catalogos()->series('100000368');

    expect($series[0]->nome)->toBe('Portaria')
        ->and($transport->calls('listarSeries'))->toHaveCount(3);
});

it('sei:ping confere a WSDL e as credenciais', function () {
    Sei::fake()->respond('listarUnidades', [(object) ['IdUnidade' => '1', 'Sigla' => 'A']]);

    // A WSDL de teste só tem 3 operações: o comando aponta as ausentes e falha.
    $this->artisan('sei:ping')
        ->expectsOutputToContain('ausentes na WSDL')
        ->expectsOutputToContain('1 unidade(s)')
        ->assertFailed();
});

it('sei:catalogo lista unidades em JSON', function () {
    Sei::fake()->respond('listarUnidades', [(object) ['IdUnidade' => '110000001', 'Sigla' => 'PROTOCOLO', 'SinProtocolo' => 'S']]);

    $this->artisan('sei:catalogo', ['tipo' => 'unidades', '--json' => true])
        ->expectsOutputToContain('"sigla": "PROTOCOLO"')
        ->assertSuccessful();

    $this->artisan('sei:catalogo', ['tipo' => 'xyz'])->assertFailed();
});

<?php

declare(strict_types=1);

use MonteiroTi\Sei\Core\Config\SeiConfig;
use MonteiroTi\Sei\Core\Config\TransportOptions;
use MonteiroTi\Sei\Core\Exceptions\AccessDeniedException;
use MonteiroTi\Sei\Core\Exceptions\ConnectionException;
use MonteiroTi\Sei\Core\Transport\SoapTransport;
use MonteiroTi\Sei\Dto\Assunto;
use MonteiroTi\Sei\Dto\Procedimento;
use MonteiroTi\Sei\Operations\SeiOperations;
use MonteiroTi\Sei\Operations\WsdlContract;
use MonteiroTi\Sei\SeiClient;
use MonteiroTi\Sei\Sip\SipOperations;
use MonteiroTi\Sei\Tests\Support\RecordingSoapClient;

function recordingClient(?RecordingSoapClient &$soap = null, string $response = ''): SeiClient
{
    $transport = new SoapTransport(
        new TransportOptions(seiFixture('sei-mini.wsdl'), wsdlCache: WSDL_CACHE_NONE),
        function (array $options) use (&$soap, $response) {
            $soap = new RecordingSoapClient(seiFixture('sei-mini.wsdl'), $options);
            $soap->responseXml = $response;

            return $soap;
        },
    );

    return new SeiClient($transport, new SeiConfig('SISTEMA', 'chave-secreta', '110000001'));
}

it('serializa argumentos posicionais e arrays de IDs com a ext-soap', function () {
    $sei = recordingClient($soap, RecordingSoapClient::envelope(
        '<ns1:enviarProcessoResponse><parametros xsi:type="xsd:boolean">true</parametros></ns1:enviarProcessoResponse>'
    ));

    $sei->processos()->enviar('12.1.000000077-4', ['110000015', '100000983']);

    $xml = new SimpleXMLElement($soap->requestXml);
    $body = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')->Body->children('Sei')->enviarProcesso->children();

    expect((string) $body->SiglaSistema)->toBe('SISTEMA')
        ->and((string) $body->IdentificacaoServico)->toBe('chave-secreta')
        ->and((string) $body->IdUnidade)->toBe('110000001')
        ->and((string) $body->ProtocoloProcedimento)->toBe('12.1.000000077-4')
        ->and(array_map('strval', iterator_to_array($body->UnidadesDestino->children(), false)))->toBe(['110000015', '100000983'])
        ->and($body->SinReabrir->attributes('http://www.w3.org/2001/XMLSchema-instance')['nil'] ?? null)->not->toBeNull();
});

it('serializa estruturas aninhadas e UTF-8 sem conversão dupla', function () {
    $sei = recordingClient($soap, RecordingSoapClient::envelope(
        '<ns1:gerarProcedimentoResponse><parametros xsi:type="ns1:RetornoGeracaoProcedimento">'
        .'<IdProcedimento xsi:type="xsd:string">1210000000774</IdProcedimento>'
        .'<ProcedimentoFormatado xsi:type="xsd:string">12.1.000000077-4</ProcedimentoFormatado>'
        .'<LinkAcesso xsi:type="xsd:string">https://sei.test/x</LinkAcesso>'
        .'</parametros></ns1:gerarProcedimentoResponse>'
    ));

    $ret = $sei->call('gerarProcedimento', [
        'Procedimento' => (new Procedimento('100000368', 'Especificação com acentuação', [new Assunto('00.01.01.01')]))->toSoap(),
    ]);

    expect($soap->requestXml)->toContain('Especificação com acentuação')
        ->and($soap->requestXml)->toContain('<CodigoEstruturado xsi:type="xsd:string">00.01.01.01</CodigoEstruturado>')
        ->and($ret->ProcedimentoFormatado)->toBe('12.1.000000077-4');
});

it('devolve lista mesmo quando o SEI retorna um único item', function () {
    $sei = recordingClient($soap, RecordingSoapClient::envelope(
        '<ns1:listarUnidadesResponse><parametros SOAP-ENC:arrayType="ns1:Unidade[1]" xsi:type="SOAP-ENC:Array">'
        .'<item xsi:type="ns1:Unidade"><IdUnidade xsi:type="xsd:string">110000001</IdUnidade><Sigla xsi:type="xsd:string">PROTOCOLO</Sigla>'
        .'<Descricao xsi:type="xsd:string">Protocolo Geral</Descricao><SinProtocolo xsi:type="xsd:string">S</SinProtocolo>'
        .'<SinArquivamento xsi:type="xsd:string">N</SinArquivamento><SinOuvidoria xsi:type="xsd:string">N</SinOuvidoria></item>'
        .'</parametros></ns1:listarUnidadesResponse>'
    ));

    $unidades = $sei->catalogos()->unidades();

    expect($unidades)->toHaveCount(1)
        ->and($unidades[0]->sigla)->toBe('PROTOCOLO')
        ->and($unidades[0]->protocolo)->toBeTrue();
});

it('converte SOAP Faults em exceções tipadas', function () {
    $sei = recordingClient($soap, RecordingSoapClient::envelope(
        '<SOAP-ENV:Fault><faultcode>SOAP-ENV:Server</faultcode>'
        .'<faultstring>Acesso negado ao serviço [Integracao] para o sistema [SISTEMA].</faultstring></SOAP-ENV:Fault>'
    ));

    expect(fn () => $sei->catalogos()->unidades())->toThrow(AccessDeniedException::class, 'Acesso negado');
});

it('reporta WSDL inacessível como ConnectionException', function () {
    $sei = new SeiClient(
        new SoapTransport(new TransportOptions('/caminho/inexistente.wsdl', wsdlCache: WSDL_CACHE_NONE)),
        new SeiConfig('S', 'K', '1'),
    );

    expect(fn () => $sei->catalogos()->unidades())->toThrow(ConnectionException::class);
});

it('confere a ordem dos parâmetros com a WSDL de teste', function () {
    $transport = new SoapTransport(new TransportOptions(seiFixture('sei-mini.wsdl'), wsdlCache: WSDL_CACHE_NONE));
    $report = WsdlContract::compare($transport->functions(), ['listarUnidades', 'enviarProcesso'], SeiOperations::parameters(...));

    expect($report)->toBe(['missing' => [], 'extra' => ['gerarProcedimento'], 'mismatched' => []]);
});

it('confere todas as operações com a WSDL real, se disponível', function () {
    $wsdl = seiFixture('sei-5.0.wsdl');
    if (! is_file($wsdl)) {
        $this->markTestSkipped('Salve a WSDL de homologação em tests/Fixtures/sei-5.0.wsdl para rodar o teste de contrato.');
    }

    $transport = new SoapTransport(new TransportOptions($wsdl, wsdlCache: WSDL_CACHE_NONE));
    $report = WsdlContract::compare($transport->functions(), SeiOperations::all(), SeiOperations::parameters(...));

    expect($report['missing'])->toBe([])
        ->and($report['mismatched'])->toBe([]);
});

it('confere as operações do SIP com a WSDL real, se disponível', function () {
    $wsdl = seiFixture('sip.wsdl');
    if (! is_file($wsdl)) {
        $this->markTestSkipped('Salve a WSDL do SIP em tests/Fixtures/sip.wsdl para rodar o teste de contrato.');
    }

    $transport = new SoapTransport(new TransportOptions($wsdl, wsdlCache: WSDL_CACHE_NONE));
    $report = WsdlContract::compare($transport->functions(), SipOperations::all(), SipOperations::parameters(...));

    expect($report['missing'])->toBe([])
        ->and($report['mismatched'])->toBe([]);
});

it('baixa a WSDL com as opções da conexão', function () {
    $transport = new SoapTransport(new TransportOptions(seiFixture('sei-mini.wsdl')));

    expect($transport->downloadWsdl())->toContain('wsdl:definitions')
        ->and(fn () => (new SoapTransport(new TransportOptions('/nao/existe.wsdl')))->downloadWsdl())
        ->toThrow(ConnectionException::class);
});

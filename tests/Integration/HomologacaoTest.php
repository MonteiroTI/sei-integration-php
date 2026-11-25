<?php

declare(strict_types=1);

/*
 * Testes contra um SEI de homologação. Rode com:
 *
 *   SEI_TEST_WSDL=https://.../sei/controlador_ws.php?servico=sei \
 *   SEI_TEST_SIGLA=... SEI_TEST_CHAVE=... SEI_TEST_UNIDADE=... \
 *   SEI_TEST_TIPO_PROCESSO=... SEI_TEST_SERIE_GERADO=... SEI_TEST_SERIE_EXTERNO=... \
 *   composer test:integration
 *
 * ATENÇÃO: cria e conclui processos reais no ambiente informado.
 *
 * Com SEI_TEST_GRAVAR=<diretório> (ou composer test:gravar), as respostas são gravadas com
 * dados pessoais mascarados, para reproduzir com Sei::fake()->reproduzir(<diretório>).
 */

use MonteiroTi\Sei\Core\Config\SeiConfig;
use MonteiroTi\Sei\Dto\ContatoRef;
use MonteiroTi\Sei\Dto\Documento;
use MonteiroTi\Sei\Dto\Procedimento;
use MonteiroTi\Sei\Options\ConsultaDocumentoOptions;
use MonteiroTi\Sei\Options\ConsultaProcedimentoOptions;
use MonteiroTi\Sei\SeiClient;
use MonteiroTi\Sei\Testing\GravadorTransport;

function homologacao(): SeiClient
{
    foreach (['SEI_TEST_WSDL', 'SEI_TEST_SIGLA', 'SEI_TEST_CHAVE', 'SEI_TEST_UNIDADE'] as $var) {
        if (! getenv($var)) {
            test()->markTestSkipped("Defina {$var} para rodar os testes de integração.");
        }
    }

    $config = [
        'wsdl' => getenv('SEI_TEST_WSDL'),
        'sigla_sistema' => getenv('SEI_TEST_SIGLA'),
        'identificacao_servico' => getenv('SEI_TEST_CHAVE'),
        'id_unidade' => getenv('SEI_TEST_UNIDADE'),
        'conteudo_charset' => getenv('SEI_TEST_CHARSET') ?: 'UTF-8',
        'verify_ssl' => getenv('SEI_TEST_VERIFY_SSL') !== '0',
        'wsdl_cache' => WSDL_CACHE_NONE,
    ];

    // composer test:gravar: grava as respostas (mascaradas) para reproduzir com Sei::fake()->reproduzir()
    $gravar = getenv('SEI_TEST_GRAVAR');
    if (is_string($gravar) && $gravar !== '') {
        return new SeiClient(new GravadorTransport(SeiClient::transportFromConfig($config), $gravar), SeiConfig::fromArray($config));
    }

    return SeiClient::create($config);
}

it('lista unidades liberadas', function () {
    expect(homologacao()->catalogos()->unidades())->not->toBeEmpty();
})->group('integration');

it('gera processo com documento gerado e externo, consulta e conclui', function () {
    $sei = homologacao();
    $tipo = getenv('SEI_TEST_TIPO_PROCESSO') ?: test()->markTestSkipped('Defina SEI_TEST_TIPO_PROCESSO.');

    $ret = $sei->processos()->gerar(new Procedimento($tipo, 'Teste de integração — acentuação ção'), array_values(array_filter([
        getenv('SEI_TEST_SERIE_GERADO') ? Documento::gerado(getenv('SEI_TEST_SERIE_GERADO'), conteudo: '<p>Conteúdo com acentuação: ação, café</p>') : null,
    ])));

    expect($ret->procedimentoFormatado)->not->toBeEmpty();

    if ($serie = getenv('SEI_TEST_SERIE_EXTERNO')) {
        $path = tempnam(sys_get_temp_dir(), 'sei').'.pdf';
        file_put_contents($path, "%PDF-1.4\n".str_repeat("% teste\n", 200000)); // ~1,6 MB
        $sei = $sei->naUnidade((string) getenv('SEI_TEST_UNIDADE'));
        try {
            $idArquivo = $sei->documentos()->enviarArquivo($path, 'teste.pdf', chunkSize: 512 * 1024);
        } finally {
            unlink($path);
        }
        $doc = $sei->documentos()->incluir(Documento::externo($serie, new DateTimeImmutable, 'teste.pdf', ContatoRef::nome('Sistema de Teste'), idArquivo: $idArquivo)
            ->noProcesso((string) $ret->procedimentoFormatado));
        expect($sei->documentos()->consultar((string) $doc->documentoFormatado, new ConsultaDocumentoOptions(andamentoGeracao: true))->idDocumento)
            ->toBe($doc->idDocumento);
    }

    $consulta = $sei->processos()->consultar((string) $ret->procedimentoFormatado, new ConsultaProcedimentoOptions(unidadesProcedimentoAberto: true));
    expect($consulta->especificacao)->toBe('Teste de integração — acentuação ção')
        ->and($consulta->aberto())->toBeTrue();

    $sei->processos()->concluir((string) $ret->procedimentoFormatado);
})->group('integration');

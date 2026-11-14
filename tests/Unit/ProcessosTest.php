<?php

declare(strict_types=1);

use MonteiroTi\Sei\Dto\Assunto;
use MonteiroTi\Sei\Dto\ContatoRef;
use MonteiroTi\Sei\Dto\DefinicaoControlePrazo;
use MonteiroTi\Sei\Dto\Documento;
use MonteiroTi\Sei\Dto\Procedimento;
use MonteiroTi\Sei\Enum\NivelAcesso;
use MonteiroTi\Sei\Options\ConsultaProcedimentoOptions;
use MonteiroTi\Sei\Options\Email;
use MonteiroTi\Sei\Options\EnviarProcessoOptions;
use MonteiroTi\Sei\Options\GerarProcedimentoOptions;

it('gera processo com documento e serializa no formato do manual', function () {
    $sei = fakeSei($t);
    $t->respond('gerarProcedimento', (object) [
        'IdProcedimento' => '1210000000774',
        'ProcedimentoFormatado' => '12.1.000000077-4',
        'LinkAcesso' => 'https://sei.test/link',
        // lista de um item chega como objeto
        'RetornoInclusaoDocumentos' => (object) ['IdDocumento' => '1140000000872', 'DocumentoFormatado' => '0003934', 'LinkAcesso' => 'l'],
    ]);

    $ret = $sei->processos()->gerar(
        new Procedimento(
            idTipoProcedimento: '100000368',
            especificacao: 'especificacao teste processo',
            assuntos: [new Assunto('00.01.01.01')],
            interessados: [ContatoRef::nome('Alberto', 'dgx'), ContatoRef::cpf('052.559.530-91', 'Paulo')],
            nivelAcesso: NivelAcesso::Publico,
        ),
        [Documento::gerado(idSerie: '3', conteudo: '<p>Olá</p>', descricao: 'Portaria')],
        new GerarProcedimentoOptions(unidadesEnvio: ['110000015', 100000983], manterAbertoUnidade: true, diasControlePrazo: 5, diasUteisControlePrazo: true),
    );

    $args = $t->lastArguments('gerarProcedimento');

    expect($ret->idProcedimento)->toBe('1210000000774')
        ->and($ret->procedimentoFormatado)->toBe('12.1.000000077-4')
        ->and($ret->documentos)->toHaveCount(1)
        ->and($ret->documentos[0]->documentoFormatado)->toBe('0003934')
        ->and($args['IdUnidade'])->toBe('110000001')
        ->and($args['Procedimento']['IdTipoProcedimento'])->toBe('100000368')
        ->and($args['Procedimento']['NivelAcesso'])->toBe(0)
        ->and($args['Procedimento']['Assuntos'])->toBe([['CodigoEstruturado' => '00.01.01.01', 'Descricao' => null]])
        ->and($args['Procedimento']['Interessados'][1])->toMatchArray(['Cpf' => '052.559.530-91', 'Nome' => 'Paulo'])
        ->and($args['Documentos'][0]['Tipo'])->toBe('G')
        ->and(base64_decode($args['Documentos'][0]['Conteudo']))->toBe('<p>Olá</p>')
        ->and($args['ProcedimentosRelacionados'])->toBe([])
        ->and($args['UnidadesEnvio'])->toBe(['110000015', '100000983'])
        ->and($args['SinManterAbertoUnidade'])->toBe('S')
        ->and($args['SinEnviarEmailNotificacao'])->toBeNull()
        ->and($args['DiasControlePrazo'])->toBe(5)
        ->and($args['SinDiasUteisControlePrazo'])->toBe('S');
});

it('envia flags S/N de consultarProcedimento e hidrata o retorno', function () {
    $sei = fakeSei($t);
    $t->respond('consultarProcedimento', (object) [
        'IdProcedimento' => '1',
        'ProcedimentoFormatado' => '12.1.000000077-4',
        'DataAutuacao' => '10/09/2026',
        'NivelAcessoGlobal' => '1',
        'TipoProcedimento' => (object) ['IdTipoProcedimento' => '100000368', 'Nome' => 'Vitaliciamento'],
        'UnidadesProcedimentoAberto' => [
            (object) ['Unidade' => (object) ['IdUnidade' => '1', 'Sigla' => 'A'], 'UsuarioAtribuicao' => null],
            (object) ['Unidade' => (object) ['IdUnidade' => '2', 'Sigla' => 'B'], 'UsuarioAtribuicao' => (object) ['IdUsuario' => '7', 'Sigla' => 'fulano']],
        ],
        'Assuntos' => [],
        'Interessados' => (object) ['Nome' => 'Maria', 'Cpf' => '1'],
        'UltimoAndamento' => (object) [
            'IdAndamento' => '5', 'IdTarefa' => '65', 'DataHora' => '11/09/2026 10:00:00',
            'Atributos' => (object) ['Nome' => 'DESCRICAO', 'Valor' => 'texto'],
        ],
    ]);

    $ret = $sei->processos()->consultar('12.1.000000077-4', new ConsultaProcedimentoOptions(unidadesProcedimentoAberto: true, ultimoAndamento: true));
    $args = $t->lastArguments('consultarProcedimento');

    expect($args['SinRetornarUnidadesProcedimentoAberto'])->toBe('S')
        ->and($args['SinRetornarAssuntos'])->toBe('N')
        ->and($ret->nivelAcessoGlobal)->toBe(NivelAcesso::Restrito)
        ->and($ret->dataAutuacao?->format('Y-m-d'))->toBe('2026-09-10')
        ->and($ret->tipoProcedimento?->nome)->toBe('Vitaliciamento')
        ->and($ret->aberto())->toBeTrue()
        ->and($ret->unidadesProcedimentoAberto[1]->usuarioAtribuicao?->sigla)->toBe('fulano')
        ->and($ret->unidadesProcedimentoAberto[0]->usuarioAtribuicao)->toBeNull()
        ->and($ret->interessados[0]->nome)->toBe('Maria')
        ->and($ret->ultimoAndamento?->atributo('DESCRICAO'))->toBe('texto');
});

it('envia o processo para várias unidades', function () {
    $sei = fakeSei($t);
    $t->respond('enviarProcesso', true);

    $sei->processos()->enviar('P', ['1', '2'], new EnviarProcessoOptions(enviarEmailNotificacao: true, diasRetornoProgramado: 10));

    expect($t->lastArguments('enviarProcesso'))->toMatchArray([
        'UnidadesDestino' => ['1', '2'],
        'SinEnviarEmailNotificacao' => 'S',
        'DiasRetornoProgramado' => 10,
        'SinManterAbertoUnidade' => null,
    ]);
});

it('envia e-mail juntando destinatários com ponto e vírgula', function () {
    $sei = fakeSei($t);
    $t->respond('enviarEmail', (object) ['IdDocumento' => '9', 'DocumentoFormatado' => '0000009', 'LinkAcesso' => 'l']);

    $ret = $sei->processos()->enviarEmail('P', new Email('a@x.gov.br', ['b@x.gov.br', 'c@x.gov.br'], 'Assunto', 'Msg', idDocumentos: ['11']));

    expect($t->lastArguments('enviarEmail'))->toMatchArray(['Para' => 'b@x.gov.br;c@x.gov.br', 'CCO' => null, 'IdDocumentos' => ['11']])
        ->and($ret->documentoFormatado)->toBe('0000009');
});

it('valida controle de prazo por data ou dias', function () {
    expect(fn () => new DefinicaoControlePrazo('P'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new DefinicaoControlePrazo('P', new DateTimeImmutable, 3))->toThrow(InvalidArgumentException::class)
        ->and(DefinicaoControlePrazo::emDias('P', 3, true)->toSoap())->toBe([
            'ProtocoloProcedimento' => 'P', 'DataPrazo' => null, 'Dias' => 3, 'SinDiasUteis' => 'S',
        ]);
});

it('aceita definições variádicas em definirControlePrazo e concluirControlePrazo', function () {
    $sei = fakeSei($t);
    $t->respond('definirControlePrazo', true)->respond('concluirControlePrazo', true);

    $sei->processos()->definirControlePrazo(
        DefinicaoControlePrazo::ate('P1', new DateTimeImmutable('2026-12-01')),
        DefinicaoControlePrazo::emDias('P2', 5),
    );
    $sei->processos()->concluirControlePrazo('P1', 'P2');

    expect($t->lastArguments('definirControlePrazo')['Definicoes'][0]['DataPrazo'])->toBe('01/12/2026')
        ->and($t->lastArguments('concluirControlePrazo')['ProtocolosProcedimentos'])->toBe(['P1', 'P2']);
});

it('retorna null em consultarProcedimentoIndividual sem resultado', function () {
    $sei = fakeSei($t);
    $t->respond('consultarProcedimentoIndividual', null);

    expect($sei->processos()->consultarIndividual('1', '2', '3', 'fulano'))->toBeNull();
});

<?php

declare(strict_types=1);

use MonteiroTi\Sei\Core\Support\SoapData;
use MonteiroTi\Sei\Dto\Andamento;
use MonteiroTi\Sei\Dto\RetornoConsultaDocumento;
use MonteiroTi\Sei\Dto\RetornoConsultaProcedimento;
use MonteiroTi\Sei\Dto\RetornoGeracaoProcedimento;
use MonteiroTi\Sei\Dto\RetornoInclusaoDocumento;
use MonteiroTi\Sei\Enum\NivelAcesso;
use MonteiroTi\Sei\Testing\Respostas;

it('processoGerado e documentoIncluido hidratam os retornos de geração e inclusão', function () {
    $ret = RetornoGeracaoProcedimento::fromSoap(SoapData::from(Respostas::processoGerado(
        '12.1.000000077-4',
        documentos: [Respostas::documentoIncluido('0003934')],
    )));

    expect($ret->idProcedimento)->toBe('1210000000774')
        ->and($ret->procedimentoFormatado)->toBe('12.1.000000077-4')
        ->and($ret->linkAcesso)->toContain('12.1.000000077-4')
        ->and($ret->documentos)->toHaveCount(1)
        ->and($ret->documentos[0]->documentoFormatado)->toBe('0003934')
        ->and($ret->documentos[0]->idDocumento)->toBe('0003934')
        ->and(RetornoInclusaoDocumento::fromSoap(SoapData::from(Respostas::documentoIncluido('5', '99'))))->idDocumento->toBe('99');
});

it('processo hidrata a consulta com unidades e andamentos', function () {
    $ret = RetornoConsultaProcedimento::fromSoap(SoapData::from(Respostas::processo(
        '12.1.000000077-4',
        unidadesAbertas: ['110000001', Respostas::unidade('110000015', 'DIAP')],
        ultimoAndamento: Respostas::andamento('1005', 65, 'Pago', new DateTimeImmutable('2026-12-01 10:00:00'), atributos: ['DESCRICAO' => 'Pago']),
        especificacao: 'Férias',
        idTipoProcedimento: '100000368',
        nivelAcesso: NivelAcesso::Restrito,
    )));

    expect($ret->aberto())->toBeTrue()
        ->and(array_map(fn ($u) => $u->unidade?->sigla, $ret->unidadesProcedimentoAberto))->toBe(['U110000001', 'DIAP'])
        ->and($ret->ultimoAndamento?->idAndamento)->toBe('1005')
        ->and($ret->ultimoAndamento?->dataHora?->format('Y-m-d H:i'))->toBe('2026-12-01 10:00')
        ->and($ret->ultimoAndamento?->atributo('DESCRICAO'))->toBe('Pago')
        ->and($ret->tipoProcedimento?->idTipoProcedimento)->toBe('100000368')
        ->and($ret->nivelAcessoLocal)->toBe(NivelAcesso::Restrito)
        ->and($ret->especificacao)->toBe('Férias');
});

it('documento hidrata a consulta com série e assinaturas', function () {
    $ret = RetornoConsultaDocumento::fromSoap(SoapData::from(Respostas::documento(
        '0003934',
        '12.1.000000077-4',
        idSerie: '3',
        nomeSerie: 'Ofício',
        assinaturas: [Respostas::assinatura('Maria Souza', cargoFuncao: 'Diretora')],
    )));

    expect($ret->documentoFormatado)->toBe('0003934')
        ->and($ret->procedimentoFormatado)->toBe('12.1.000000077-4')
        ->and($ret->serie?->nome)->toBe('Ofício')
        ->and($ret->assinaturas[0]->sigla)->toBe('maria')
        ->and($ret->assinaturas[0]->cargoFuncao)->toBe('Diretora');
});

it('unidades, series e andamento servem às listagens', function () {
    $sei = fakeSei($transport);
    $transport->respond('listarUnidades', Respostas::unidades(['110000001' => 'PROTOCOLO']))
        ->respond('listarSeries', Respostas::series(['3' => 'Ofício']))
        ->respond('listarAndamentos', [Respostas::andamento('7', 1000)]);

    expect($sei->catalogos()->unidades()[0]->sigla)->toBe('PROTOCOLO')
        ->and($sei->catalogos()->series()[0]->nome)->toBe('Ofício')
        ->and($sei->andamentos()->listar('P', tarefas: [1000]))->toEqual([Andamento::fromSoap(SoapData::from(Respostas::andamento('7', 1000)))]);
});

<?php

declare(strict_types=1);

use MonteiroTi\Sei\Core\Exceptions\InvalidArgumentException;
use MonteiroTi\Sei\Dto\ContatoRef;
use MonteiroTi\Sei\Dto\Documento;
use MonteiroTi\Sei\Dto\SecaoDocumento;
use MonteiroTi\Sei\Enum\NivelAcesso;

it('serializa um documento externo com todos os campos da estrutura', function () {
    $doc = Documento::externo(
        idSerie: '301',
        data: new DateTimeImmutable('2011-09-10'),
        nomeArquivo: 'oficio.txt',
        remetente: ContatoRef::nome('Luiza', 'lmr'),
        conteudo: 'conteudo do documento oficio.txt',
        numero: '1000',
        interessados: [ContatoRef::cnpj('72.776.522/0001-90')],
        nivelAcesso: NivelAcesso::Restrito,
        idHipoteseLegal: '34',
    )->noProcesso('12.1.000000077-4');

    $soap = $doc->toSoap();

    expect(array_keys($soap))->toBe([
        'Tipo', 'IdProcedimento', 'ProtocoloProcedimento', 'IdSerie', 'Numero', 'NomeArvore', 'DinValor', 'Data',
        'Descricao', 'IdTipoConferencia', 'SinArquivamento', 'Remetente', 'Interessados', 'Destinatarios',
        'Observacao', 'NomeArquivo', 'NivelAcesso', 'IdHipoteseLegal', 'Conteudo', 'ConteudoSecoes',
        'ConteudoMTOM', 'IdArquivo', 'Campos', 'SinBloqueado', 'IdItemEtapa',
    ])
        ->and($soap['Tipo'])->toBe('R')
        ->and($soap['ProtocoloProcedimento'])->toBe('12.1.000000077-4')
        ->and($soap['Data'])->toBe('10/09/2011')
        ->and($soap['Remetente'])->toMatchArray(['Sigla' => 'lmr', 'Nome' => 'Luiza'])
        ->and($soap['Destinatarios'])->toBe([])
        ->and($soap['NivelAcesso'])->toBe(1)
        ->and(base64_decode($soap['Conteudo']))->toBe('conteudo do documento oficio.txt');
});

it('exige data, nome do arquivo, remetente e um conteúdo em documentos externos', function () {
    $base = fn (array $o) => Documento::externo(...array_merge([
        'idSerie' => '1', 'data' => new DateTimeImmutable, 'nomeArquivo' => 'a.pdf', 'remetente' => ContatoRef::nome('X'),
    ], $o));

    expect(fn () => $base([]))->toThrow(InvalidArgumentException::class, 'exatamente um')
        ->and(fn () => $base(['conteudo' => 'x', 'idArquivo' => '1']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $base(['nomeArquivo' => '', 'conteudo' => 'x']))->toThrow(InvalidArgumentException::class, 'nomeArquivo')
        ->and($base(['idArquivo' => '55'])->toSoap()['IdArquivo'])->toBe('55');
});

it('converte o HTML de documentos gerados para ISO-8859-1 quando configurado', function () {
    $doc = Documento::gerado('3', conteudo: 'ação');

    expect(base64_decode($doc->toSoap('ISO-8859-1')['Conteudo']))->toBe(mb_convert_encoding('ação', 'ISO-8859-1', 'UTF-8'))
        ->and(base64_decode($doc->toSoap()['Conteudo']))->toBe('ação');
});

it('serializa seções e impede conteúdo e seções juntos', function () {
    $doc = Documento::gerado('3', secoes: [new SecaoDocumento('Ementa', 'Texto')]);

    expect($doc->toSoap()['ConteudoSecoes'][0])->toBe(['Nome' => 'Ementa', 'Conteudo' => base64_encode('Texto')])
        ->and($doc->toSoap()['Conteudo'])->toBeNull()
        ->and(fn () => Documento::gerado('3', conteudo: 'x', secoes: [new SecaoDocumento('A', 'B')])->toSoap())
        ->toThrow(InvalidArgumentException::class);
});

it('exige identificador em ContatoRef', function () {
    expect(fn () => (new ContatoRef)->toSoap())->toThrow(InvalidArgumentException::class);
});

it('incluirDocumento exige o processo de destino', function () {
    $sei = fakeSei($t);
    $t->respond('incluirDocumento', (object) ['IdDocumento' => '1', 'DocumentoFormatado' => '0000001']);

    expect(fn () => $sei->documentos()->incluir(Documento::gerado('3', conteudo: 'x')))->toThrow(InvalidArgumentException::class);

    $ret = $sei->documentos()->incluir(Documento::gerado('3', conteudo: 'x')->noProcessoId('1210000000774'));
    expect($ret->documentoFormatado)->toBe('0000001')
        ->and($t->lastArguments('incluirDocumento')['Documento']['IdProcedimento'])->toBe('1210000000774');
});

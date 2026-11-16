<?php

declare(strict_types=1);

use MonteiroTi\Sei\Core\Support\Redactor;

it('mascara dados pessoais dentro de contatos e mantém nomes técnicos', function () {
    $args = Redactor::redact([
        'SiglaSistema' => 'SISTEMA',
        'IdentificacaoServico' => 'segredo',
        'Procedimento' => [
            'Especificacao' => 'Requerimento',
            'Interessados' => [
                ['Nome' => 'Paulo da Silva', 'Cpf' => '052.559.530-91', 'Sigla' => 'paulo'],
                ['Nome' => 'ACME Ltda.', 'Cnpj' => '14743019000132'],
            ],
        ],
        'Documentos' => [[
            'NomeArquivo' => 'oficio.pdf',
            'Remetente' => ['Nome' => 'Luiza Souza', 'IdContato' => '100280566'],
            'Campos' => [['Nome' => 'Valor', 'Valor' => '10']],
        ]],
    ]);

    expect($args['IdentificacaoServico'])->toBe('***')
        ->and($args['SiglaSistema'])->toBe('SISTEMA')
        ->and($args['Procedimento']['Especificacao'])->toBe('Requerimento')
        ->and($args['Procedimento']['Interessados'][0])->toBe(['Nome' => 'P. D. S.', 'Cpf' => '***.***.***-91', 'Sigla' => '***'])
        ->and($args['Procedimento']['Interessados'][1]['Cnpj'])->toBe('**.***.***/****-32')
        ->and($args['Documentos'][0]['Remetente'])->toBe(['Nome' => 'L. S.', 'IdContato' => '100280566'])
        ->and($args['Documentos'][0]['Campos'][0]['Nome'])->toBe('Valor')
        ->and($args['Documentos'][0]['NomeArquivo'])->toBe('oficio.pdf');
});

it('trata a raiz de registrarOuvidoria como dados do cidadão', function () {
    $args = Redactor::redact([
        'Nome' => 'Maria Souza',
        'Email' => 'maria@exemplo.com',
        'Telefone' => '61 99999-0000',
        'Mensagem' => 'Texto da manifestação',
        'IdTipoProcedimento' => '100000400',
    ], 'registrarOuvidoria');

    expect($args)->toBe([
        'Nome' => 'M. S.',
        'Email' => 'm***@exemplo.com',
        'Telefone' => '***',
        'Mensagem' => '[21 caracteres]',
        'IdTipoProcedimento' => '100000400',
    ]);

    expect(Redactor::redact(['Nome' => 'arquivo.pdf'], 'adicionarArquivo'))->toBe(['Nome' => 'arquivo.pdf']);
});

it('mascara listas de e-mails de enviarEmail', function () {
    expect(Redactor::redact(['Para' => 'ana@x.gov.br;bruno@y.gov.br'])['Para'])->toBe('a***@x.gov.br;b***@y.gov.br');
});

it('remove ou mantém dados pessoais conforme o modo, sempre ocultando credenciais', function () {
    $args = ['ChaveAcesso' => 'k', 'Cpf' => '05255953091', 'IdSistema' => '1', 'CampoX' => 'segredo'];

    expect((new Redactor(Redactor::MODO_REMOVER, ['CampoX']))->arguments($args))->toBe(['ChaveAcesso' => '***', 'IdSistema' => '1'])
        ->and((new Redactor(Redactor::MODO_MANTER))->arguments($args))->toBe(['ChaveAcesso' => '***', 'Cpf' => '05255953091', 'IdSistema' => '1', 'CampoX' => 'segredo'])
        ->and(fn () => new Redactor('outro'))->toThrow(InvalidArgumentException::class);
});

it('mascara CPF, CNPJ e e-mail em mensagens sem tocar em protocolos do SEI', function () {
    $msg = 'CPF 052.559.530-91 e 05255953091, CNPJ 14.743.019/0001-32, e-mail fulano@orgao.gov.br; processo 12.1.000000077-4, NUP 00000.000001/2026-11, id 1210000000774.';

    expect(Redactor::default()->text($msg))->toBe(
        'CPF ***.***.***-91 e ***.***.***-91, CNPJ **.***.***/****-32, e-mail f***@orgao.gov.br; processo 12.1.000000077-4, NUP 00000.000001/2026-11, id 1210000000774.'
    );
});

it('resume conteúdos longos', function () {
    expect(Redactor::redact(['Conteudo' => str_repeat('A', 1000)])['Conteudo'])->toStartWith('[1000 bytes]');
});

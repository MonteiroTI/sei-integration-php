<?php

declare(strict_types=1);

use MonteiroTi\Sei\Core\Config\SipConfig;
use MonteiroTi\Sei\Core\Exceptions\InvalidArgumentException;
use MonteiroTi\Sei\Core\Transport\FakeTransport;
use MonteiroTi\Sei\Sip\Dto\FiltroPermissao;
use MonteiroTi\Sei\Sip\Dto\Permissao;
use MonteiroTi\Sei\Sip\Dto\Usuario;
use MonteiroTi\Sei\Sip\Enum\OperacaoPermissao;
use MonteiroTi\Sei\Sip\Enum\OperacaoUsuario;
use MonteiroTi\Sei\Sip\SipClient;

it('replica usuários e permissões com a chave de acesso', function () {
    $t = new FakeTransport;
    $sip = new SipClient($t, new SipConfig('chave-sip', '100000100'));
    $t->respond('replicarUsuario', true)->respond('replicarPermissao', true);

    $sip->replicarUsuario([new Usuario(OperacaoUsuario::Cadastrar, '123', '0', 'fulano', 'Fulano de Tal', cpf: '1', email: 'f@x')]);
    $sip->replicarPermissao(new Permissao(
        OperacaoPermissao::Atualizar, idOrgaoUsuario: '0', idOrigemUsuario: '123', idOrgaoUnidade: '0',
        idOrigemUnidade: 'U1', idPerfil: '9', dataInicial: new DateTimeImmutable('2026-01-01'), subunidades: false,
    ));

    expect($t->lastArguments('replicarUsuario'))->toMatchArray(['ChaveAcesso' => 'chave-sip', 'SinConsiderarOrgao' => 'N'])
        ->and($t->lastArguments('replicarUsuario')['Usuarios'][0]['StaOperacao'])->toBe('C')
        ->and($t->lastArguments('replicarPermissao')['Permissoes'][0])->toMatchArray([
            'IdSistema' => '100000100', 'DataInicial' => '01/01/2026', 'SinSubunidades' => 'N',
        ]);
});

it('lista permissões usando o sistema padrão', function () {
    $t = new FakeTransport;
    $sip = new SipClient($t, new SipConfig('chave-sip', '100000100'));
    $t->respond('listarPermissao', [(object) ['IdSistema' => '100000100', 'IdUsuario' => '5', 'DataInicial' => '01/01/2026', 'SinSubunidades' => 'S']]);

    $permissoes = $sip->listarPermissao(new FiltroPermissao(idUsuario: '5'));

    expect($permissoes[0]->subunidades)->toBeTrue()
        ->and($permissoes[0]->dataInicial?->format('Y-m-d'))->toBe('2026-01-01')
        ->and(array_keys($t->lastArguments('listarPermissao'))[0])->toBe('ChaveAcesso');
});

it('valida permissões antes de enviar', function () {
    expect(fn () => (new Permissao(OperacaoPermissao::Atualizar, idUsuario: '1', idUnidade: '2'))->toSoap())
        ->toThrow(InvalidArgumentException::class, 'dataInicial')
        ->and(fn () => (new Permissao(OperacaoPermissao::Excluir, idUnidade: '2'))->toSoap())
        ->toThrow(InvalidArgumentException::class, 'idUsuario');
});

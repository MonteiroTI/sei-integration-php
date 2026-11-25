<?php

declare(strict_types=1);

use MonteiroTi\Sei\Core\Config\SeiConfig;
use MonteiroTi\Sei\Core\Exceptions\AccessDeniedException;
use MonteiroTi\Sei\Core\Exceptions\ConnectionException;
use MonteiroTi\Sei\Core\Exceptions\FaultException;
use MonteiroTi\Sei\Core\Transport\FakeTransport;
use MonteiroTi\Sei\Dto\ContatoRef;
use MonteiroTi\Sei\Dto\Procedimento;
use MonteiroTi\Sei\Options\ConsultaDocumentoOptions;
use MonteiroTi\Sei\SeiClient;
use MonteiroTi\Sei\Testing\GravadorTransport;
use MonteiroTi\Sei\Testing\Respostas;
use MonteiroTi\Sei\Testing\SeiSimulado;

function diretorioTemporario(): string
{
    $dir = sys_get_temp_dir().'/sei-gravacoes-'.bin2hex(random_bytes(4));

    return $dir;
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/sei-gravacoes-*/*.json') ?: [] as $f) {
        unlink($f);
    }
    foreach (glob(sys_get_temp_dir().'/sei-gravacoes-*') ?: [] as $d) {
        rmdir($d);
    }
});

it('grava as chamadas sem credenciais e com dados pessoais mascarados', function () {
    $dir = diretorioTemporario();
    $origem = (new FakeTransport)
        ->respond('gerarProcedimento', Respostas::processoGerado('12.1.000000077-4'))
        ->respond('consultarDocumento', Respostas::documento('0003934', '12.1.000000077-4',
            assinaturas: [Respostas::assinatura('Maria Souza', 'maria.souza')],
        ));
    $sei = new SeiClient(new GravadorTransport($origem, $dir), new SeiConfig('SISTEMA', 'chave-secreta', '110000001'));

    $sei->processos()->gerar(new Procedimento('1', 'Férias de Paulo, CPF 052.559.530-91', interessados: [ContatoRef::cpf('052.559.530-91', 'Paulo Silva')]));
    $sei->documentos()->consultar('0003934', new ConsultaDocumentoOptions(assinaturas: true));

    $arquivos = glob($dir.'/*.json');
    expect(array_map('basename', $arquivos))->toBe(['001-gerarProcedimento.json', '002-consultarDocumento.json']);

    $conteudo = file_get_contents($arquivos[0]).file_get_contents($arquivos[1]);
    expect($conteudo)->not->toContain('chave-secreta')
        ->not->toContain('052.559.530-91')
        ->not->toContain('Paulo Silva')
        ->not->toContain('Maria Souza')
        ->not->toContain('maria.souza')
        ->toContain('***.***.***-91')
        ->toContain('"Nome": "M. S."')
        ->toContain('12.1.000000077-4');
});

it('reproduz as gravações na mesma ordem e com o mesmo resultado', function () {
    $dir = diretorioTemporario();
    $origem = new FakeTransport;
    $origem->simular(new SeiSimulado);
    $gravando = new SeiClient(new GravadorTransport($origem, $dir), new SeiConfig('S', 'k', '110000001'));

    $gerado = $gravando->processos()->gerar(new Procedimento('1'));
    $consulta = $gravando->catalogos()->unidades();

    $reproduzido = fakeSei($fake);
    $fake->reproduzir($dir);

    $siglas = fn (array $unidades) => array_map(fn ($u) => [$u->idUnidade, $u->sigla], $unidades);

    // Textos livres (Descricao) são mascarados na gravação; o resto volta igual.
    expect($reproduzido->processos()->gerar(new Procedimento('1')))->toEqual($gerado)
        ->and($siglas($reproduzido->catalogos()->unidades()))->toBe($siglas($consulta))
        ->and($reproduzido->catalogos()->unidades()[0]->descricao)->toBe('***');
});

it('grava e reproduz erros como a exceção correspondente', function () {
    $dir = diretorioTemporario();
    $origem = (new FakeTransport)
        ->respond('concluirProcesso', new FaultException('Processo de joao@orgao.gov.br já concluído.', 'concluirProcesso'))
        ->respond('reabrirProcesso', new AccessDeniedException('Acesso negado.', 'reabrirProcesso'))
        ->respond('consultarProcedimento', new ConnectionException('Could not connect to host', 'consultarProcedimento'));
    $gravando = new SeiClient(new GravadorTransport($origem, $dir), new SeiConfig('S', 'k', '1'));

    foreach ([fn () => $gravando->processos()->concluir('P'), fn () => $gravando->processos()->reabrir('P'), fn () => $gravando->processos()->consultar('P')] as $chamada) {
        try {
            $chamada();
        } catch (Throwable) {
        }
    }
    expect(file_get_contents(glob($dir.'/001-*.json')[0]))->toContain('j***@orgao.gov.br')->not->toContain('joao@');

    $sei = fakeSei($fake);
    $fake->reproduzir($dir);

    expect(fn () => $sei->processos()->concluir('P'))->toThrow(FaultException::class, 'já concluído')
        ->and(fn () => $sei->processos()->reabrir('P'))->toThrow(AccessDeniedException::class)
        ->and(fn () => $sei->processos()->consultar('P'))->toThrow(ConnectionException::class);
});

it('reproduz a gravação sintética versionada', function () {
    $sei = fakeSei($fake);
    $fake->reproduzir(seiFixture('gravacoes-sinteticas'));

    $ret = $sei->processos()->gerar(new Procedimento('1'));
    $consulta = $sei->processos()->consultar((string) $ret->procedimentoFormatado);

    expect($ret->procedimentoFormatado)->toBe('12.1.000000077-4')
        ->and($consulta->especificacao)->toBe('***')
        ->and($consulta->aberto())->toBeTrue()
        ->and(fn () => (new FakeTransport)->reproduzir(sys_get_temp_dir().'/nao-existe'))->toThrow(InvalidArgumentException::class);
});

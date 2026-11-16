<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use MonteiroTi\Sei\Core\Exceptions\ConnectionException;
use MonteiroTi\Sei\Core\Exceptions\FaultException;
use MonteiroTi\Sei\Dto\Documento;
use MonteiroTi\Sei\Dto\Procedimento;
use MonteiroTi\Sei\Enum\NivelAcesso;
use MonteiroTi\Sei\Laravel\Facades\Sei;
use MonteiroTi\Sei\Laravel\Integracao\OperacaoEmAndamentoException;
use MonteiroTi\Sei\Laravel\Integracao\OperacaoIncertaException;
use MonteiroTi\Sei\Laravel\Jobs\GerarProcessoSeiJob;
use MonteiroTi\Sei\Laravel\Jobs\IncluirDocumentoSeiJob;
use MonteiroTi\Sei\Laravel\Models\SeiOperacao;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;
use MonteiroTi\Sei\Laravel\Models\StatusOperacao;
use MonteiroTi\Sei\Tests\Support\Requerimento;

function retornoGeracao(): object
{
    return (object) [
        'IdProcedimento' => '1210000000774',
        'ProcedimentoFormatado' => '12.1.000000077-4',
        'LinkAcesso' => 'https://sei.test/p',
        'RetornoInclusaoDocumentos' => [(object) ['IdDocumento' => '1140000000872', 'DocumentoFormatado' => '0003934', 'LinkAcesso' => 'https://sei.test/d']],
    ];
}

function timeout(): ConnectionException
{
    return new ConnectionException('Error Fetching http headers', 'gerarProcedimento', possivelmenteEnviada: true);
}

it('gera o processo uma única vez e grava os vínculos', function () {
    config(['sei.integracao.sistema' => 'RH']);
    $fake = Sei::fake()->respond('gerarProcedimento', retornoGeracao());
    $req = Requerimento::create(['assunto' => 'Férias']);
    $proc = new Procedimento('100000368', 'Férias', observacao: 'Urgente');

    $processo = Sei::integracao()->gerarProcesso($req, $proc, [Documento::gerado('3', conteudo: '<p>x</p>')]);
    $denovo = Sei::integracao()->gerarProcesso($req, $proc, [Documento::gerado('3', conteudo: '<p>x</p>')]);

    $fake->assertCalledTimes('gerarProcedimento', 1)
        ->assertCalled('gerarProcedimento', fn ($a) => $a['Procedimento']['Observacao'] === 'Urgente [RH Requerimento #'.$req->id.']');

    expect($denovo->is($processo))->toBeTrue()
        ->and($processo->protocolo_formatado)->toBe('12.1.000000077-4')
        ->and($processo->id_unidade_geradora)->toBe('110000001')
        ->and($processo->vinculavel->is($req))->toBeTrue()
        ->and($processo->documentos->first()->documento_formatado)->toBe('0003934')
        ->and($processo->documentos->first()->tipo)->toBe('G')
        ->and($processo->sincronizar)->toBeTrue()
        ->and(SeiOperacao::first()->status)->toBe(StatusOperacao::Concluida)
        ->and(SeiOperacao::first()->chave)->toBe('default:'.$req->getMorphClass().':'.$req->id.':gerarProcedimento');
});

it('marca como incerta um timeout após o envio e não repete a operação', function () {
    $fake = Sei::fake()->respond('gerarProcedimento', timeout());
    $req = Requerimento::create();

    expect(fn () => Sei::integracao()->gerarProcesso($req, new Procedimento('1')))->toThrow(OperacaoIncertaException::class)
        ->and(fn () => Sei::integracao()->gerarProcesso($req, new Procedimento('1')))->toThrow(OperacaoIncertaException::class);

    $fake->assertCalledTimes('gerarProcedimento', 1);
    expect(SeiOperacao::first()->status)->toBe(StatusOperacao::Incerta)
        ->and(SeiOperacao::first()->erro)->toContain('Error Fetching http headers');
});

it('resolve operação incerta com o processo encontrado no SEI', function () {
    $fake = Sei::fake()
        ->respond('gerarProcedimento', timeout())
        ->respond('consultarProcedimento', (object) ['IdProcedimento' => '9', 'ProcedimentoFormatado' => '12.1.000000099-9', 'LinkAcesso' => 'l']);
    $req = Requerimento::create();

    rescue(fn () => Sei::integracao()->gerarProcesso($req, new Procedimento('1')), report: false);
    $chave = SeiOperacao::first()->chave;

    $this->artisan('sei:operacoes')->expectsOutputToContain('incerta')->assertSuccessful();
    $this->artisan('sei:operacoes:resolver', ['chave' => $chave, '--protocolo' => '12.1.000000099-9'])->assertSuccessful();

    $processo = Sei::integracao()->gerarProcesso($req, new Procedimento('1'));

    $fake->assertCalledTimes('gerarProcedimento', 1);
    expect($processo->protocolo_formatado)->toBe('12.1.000000099-9')
        ->and($processo->vinculavel->is($req))->toBeTrue()
        ->and(SeiOperacao::first()->status)->toBe(StatusOperacao::Concluida);
});

it('permite nova tentativa depois de descartar a operação incerta', function () {
    $fake = Sei::fake()->respond('gerarProcedimento', timeout(), retornoGeracao());
    $req = Requerimento::create();

    rescue(fn () => Sei::integracao()->gerarProcesso($req, new Procedimento('1')), report: false);
    $this->artisan('sei:operacoes:resolver', ['chave' => SeiOperacao::first()->chave, '--descartar' => true])->assertSuccessful();

    expect(Sei::integracao()->gerarProcesso($req, new Procedimento('1'))->protocolo_formatado)->toBe('12.1.000000077-4');
    $fake->assertCalledTimes('gerarProcedimento', 2);
});

it('repete operações que não chegaram ao SEI ou que o SEI recusou', function (Throwable $erro) {
    $fake = Sei::fake()->respond('gerarProcedimento', $erro, retornoGeracao());
    $req = Requerimento::create();

    expect(fn () => Sei::integracao()->gerarProcesso($req, new Procedimento('1')))->toThrow($erro::class);
    expect(SeiOperacao::first()->status)->toBe(StatusOperacao::Falhou);

    Sei::integracao()->gerarProcesso($req, new Procedimento('1'));

    $fake->assertCalledTimes('gerarProcedimento', 2);
    expect(SeiOperacao::first())->status->toBe(StatusOperacao::Concluida)->tentativas->toBe(2);
})->with([
    'sem conexão' => fn () => new ConnectionException('Could not connect to host', 'gerarProcedimento', possivelmenteEnviada: false),
    'recusada pelo SEI' => fn () => new FaultException('Tipo de processo não liberado', 'gerarProcedimento'),
]);

it('repete consultas mesmo após timeout', function () {
    $fake = Sei::fake()->respond('concluirProcesso', new ConnectionException('timeout', 'concluirProcesso', possivelmenteEnviada: true), true);
    $processo = SeiProcesso::create(['conexao' => 'default', 'id_procedimento' => '1', 'protocolo_formatado' => 'P']);

    expect(fn () => Sei::integracao()->concluirProcesso($processo, 'c1'))->toThrow(ConnectionException::class);
    Sei::integracao()->concluirProcesso($processo, 'c1');

    $fake->assertCalledTimes('concluirProcesso', 2);
});

it('impede execução concorrente com a mesma chave', function () {
    Sei::fake()->respond('gerarProcedimento', retornoGeracao());
    $req = Requerimento::create();
    $lock = Cache::lock('sei:operacao:'.sha1('default:'.$req->getMorphClass().':'.$req->id.':gerarProcedimento'), 60);
    $lock->get();

    expect(fn () => Sei::integracao()->gerarProcesso($req, new Procedimento('1')))->toThrow(OperacaoEmAndamentoException::class);
    $lock->release();
});

it('trata execução interrompida como incerta', function () {
    $fake = Sei::fake()->respond('gerarProcedimento', retornoGeracao());
    $req = Requerimento::create();
    SeiOperacao::create([
        'chave' => 'default:'.$req->getMorphClass().':'.$req->id.':gerarProcedimento',
        'conexao' => 'default', 'operacao' => 'gerarProcedimento', 'status' => StatusOperacao::Executando,
    ]);

    expect(fn () => Sei::integracao()->gerarProcesso($req, new Procedimento('1')))->toThrow(OperacaoIncertaException::class);
    $fake->assertNotCalled('gerarProcedimento');
});

it('lança andamento de rastreabilidade sem desfazer a operação se ele falhar', function () {
    config(['sei.integracao.sistema' => 'RH', 'sei.integracao.andamento.ativo' => true]);
    $fake = Sei::fake()
        ->respond('gerarProcedimento', retornoGeracao())
        ->respond('lancarAndamento', new FaultException('falhou', 'lancarAndamento'), (object) ['IdAndamento' => '1']);
    $req = Requerimento::create();

    $processo = Sei::integracao()->gerarProcesso($req, new Procedimento('1'));
    Sei::integracao()->gerarProcesso($req, new Procedimento('1')); // nova tentativa só do andamento

    $fake->assertCalledTimes('gerarProcedimento', 1)
        ->assertCalledTimes('lancarAndamento', 2)
        ->assertCalled('lancarAndamento', fn ($a) => $a['Atributos'][0]['Valor'] === "Processo gerado pelo sistema RH (ref. Requerimento #{$req->id}).");
    expect($processo->exists)->toBeTrue();
});

it('não sincroniza processos sigilosos', function () {
    Sei::fake()->respond('gerarProcedimento', retornoGeracao());

    $processo = Sei::integracao()->gerarProcesso(Requerimento::create(), new Procedimento('1', nivelAcesso: NivelAcesso::Sigiloso, idHipoteseLegal: '1'));

    expect($processo)->sigiloso->toBeTrue()->sincronizar->toBeFalse()->nivel_acesso->toBe(2);
});

it('inclui documento pelo model do sistema, uma vez por chave', function () {
    $fake = Sei::fake()
        ->respond('gerarProcedimento', retornoGeracao())
        ->respond('incluirDocumento', (object) ['IdDocumento' => '5', 'DocumentoFormatado' => '0000005']);
    $req = Requerimento::create();
    Sei::integracao()->gerarProcesso($req, new Procedimento('1'));

    $doc = Sei::integracao()->incluirDocumento($req, Documento::gerado('3', conteudo: 'x'), 'despacho-1');
    Sei::integracao()->incluirDocumento($req, Documento::gerado('3', conteudo: 'x'), 'despacho-1');

    $fake->assertCalledTimes('incluirDocumento', 1)
        ->assertCalled('incluirDocumento', fn ($a) => $a['Documento']['ProtocoloProcedimento'] === '12.1.000000077-4');
    expect($doc->documento_formatado)->toBe('0000005')
        ->and($doc->processo->protocolo_formatado)->toBe('12.1.000000077-4')
        ->and(fn () => Sei::integracao()->incluirDocumento(Requerimento::create(), Documento::gerado('3', conteudo: 'x'), 'k'))
        ->toThrow(InvalidArgumentException::class, 'não tem processo');
});

it('executa os jobs com idempotência', function () {
    $fake = Sei::fake()
        ->respond('gerarProcedimento', retornoGeracao())
        ->respond('incluirDocumento', (object) ['IdDocumento' => '5', 'DocumentoFormatado' => '0000005']);
    $req = Requerimento::create();

    GerarProcessoSeiJob::dispatchSync($req, new Procedimento('1'));
    GerarProcessoSeiJob::dispatchSync($req, new Procedimento('1'));
    IncluirDocumentoSeiJob::dispatchSync($req, Documento::gerado('3', conteudo: 'x'), 'doc-1');

    $fake->assertCalledTimes('gerarProcedimento', 1)->assertCalledTimes('incluirDocumento', 1);
    expect($req->seiProcesso->documentos)->toHaveCount(2)
        ->and((new GerarProcessoSeiJob($req, new Procedimento('1')))->tries)->toBe(1);
});

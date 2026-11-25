<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use MonteiroTi\Sei\Core\Exceptions\AccessDeniedException;
use MonteiroTi\Sei\Core\Exceptions\FaultException;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\DocumentoSeiAssinado;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\DocumentoSeiPublicado;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\NovoAndamentoSei;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\ProcessoSeiConcluido;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\ProcessoSeiReaberto;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\ProcessoSeiTramitado;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\PublicacaoSeiAgendada;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\SincronizacaoSeiFalhou;
use MonteiroTi\Sei\Laravel\Facades\Sei;
use MonteiroTi\Sei\Laravel\Jobs\SincronizarProcessoSeiJob;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTi\Sei\Laravel\SeiServiceProvider;

const EVENTOS_SINCRONIZACAO = [
    NovoAndamentoSei::class, ProcessoSeiTramitado::class, ProcessoSeiConcluido::class, ProcessoSeiReaberto::class,
    DocumentoSeiAssinado::class, PublicacaoSeiAgendada::class, DocumentoSeiPublicado::class, SincronizacaoSeiFalhou::class,
];

function consultaProcesso(array $unidades, ?string $ultimoAndamento = '10', ?string $conclusao = null): object
{
    return (object) [
        'IdProcedimento' => '1210000000774',
        'ProcedimentoFormatado' => '12.1.000000077-4',
        'UnidadesProcedimentoAberto' => array_map(fn ($id) => (object) ['Unidade' => (object) ['IdUnidade' => $id, 'Sigla' => "U{$id}"]], $unidades),
        'UltimoAndamento' => $ultimoAndamento === null ? null : (object) ['IdAndamento' => $ultimoAndamento, 'DataHora' => '01/12/2026 10:00:00', 'Descricao' => 'texto'],
        'AndamentoConclusao' => $conclusao === null ? null : (object) ['IdAndamento' => $conclusao, 'DataHora' => '02/12/2026 09:00:00'],
    ];
}

function processoVinculado(array $extra = []): SeiProcesso
{
    return SeiProcesso::create([
        'conexao' => 'default',
        'id_procedimento' => '1210000000774',
        'protocolo_formatado' => '12.1.000000077-4',
        'id_unidade_geradora' => '110000001',
        ...$extra,
    ]);
}

it('registra o estado na primeira sincronização sem disparar eventos', function () {
    Event::fake(EVENTOS_SINCRONIZACAO);
    $fake = Sei::fake()->respond('consultarProcedimento', consultaProcesso(['1']));
    $processo = processoVinculado();

    Sei::sincronizador()->processo($processo);

    Event::assertNothingDispatched();
    $fake->assertCalled('consultarProcedimento', fn ($a) => $a['SinRetornarUnidadesProcedimentoAberto'] === 'S'
        && $a['SinRetornarUltimoAndamento'] === 'S' && $a['IdUnidade'] === '110000001');
    expect($processo->refresh())
        ->idsUnidadesAbertas()->toBe(['1'])
        ->ultimo_andamento_id->toBe('10')
        ->aberto->toBeTrue()
        ->and($processo->proxima_sincronizacao_em->diffInSeconds(now()->addSeconds(900)))->toBeLessThan(5);
});

it('dispara eventos de andamento, tramitação, conclusão e reabertura', function () {
    Event::fake(EVENTOS_SINCRONIZACAO);
    Sei::fake()->respond('consultarProcedimento',
        consultaProcesso(['1']),
        consultaProcesso(['1', '2'], '11'),
        consultaProcesso([], '12', '12'),
        consultaProcesso(['2'], '13'),
    );
    $processo = processoVinculado();
    $sync = Sei::sincronizador();

    $sync->processo($processo); // estado inicial
    $sync->processo($processo);
    Event::assertDispatched(NovoAndamentoSei::class, fn ($e) => $e->andamento->idAndamento === '11');
    Event::assertDispatched(ProcessoSeiTramitado::class, fn ($e) => $e->entrou() === ['2'] && $e->saiu() === []);

    $sync->processo($processo);
    Event::assertDispatched(ProcessoSeiConcluido::class, fn ($e) => $e->andamentoConclusao?->idAndamento === '12');
    expect($processo->refresh()->aberto)->toBeFalse()
        ->and($processo->concluido_em->format('Y-m-d'))->toBe('2026-12-02')
        ->and($processo->proxima_sincronizacao_em->diffInSeconds(now()->addDay()))->toBeLessThan(5);

    $sync->processo($processo);
    Event::assertDispatched(ProcessoSeiReaberto::class);
    expect($processo->refresh()->concluido_em)->toBeNull();
});

it('acompanha assinaturas e publicação dos documentos', function () {
    Event::fake(EVENTOS_SINCRONIZACAO);
    Sei::fake()
        ->respond('consultarProcedimento', consultaProcesso(['1']))
        ->respond('consultarDocumento',
            (object) ['IdDocumento' => '5', 'Assinaturas' => []],
            (object) ['IdDocumento' => '5', 'Assinaturas' => [(object) ['Nome' => 'Ana', 'Sigla' => 'ana', 'DataHora' => '03/12/2026 10:00:00']]],
            (object) [
                'IdDocumento' => '5',
                'Assinaturas' => [(object) ['Nome' => 'Ana', 'Sigla' => 'ana', 'DataHora' => '03/12/2026 10:00:00']],
                'Publicacao' => (object) ['IdPublicacao' => '7', 'Estado' => 'A'],
            ],
            (object) [
                'IdDocumento' => '5',
                'Assinaturas' => [(object) ['Nome' => 'Ana', 'Sigla' => 'ana', 'DataHora' => '03/12/2026 10:00:00']],
                'Publicacao' => (object) ['IdPublicacao' => '7', 'Estado' => 'P', 'DataPublicacao' => '05/12/2026'],
            ],
        );
    $processo = processoVinculado();
    $documento = $processo->documentos()->create(['conexao' => 'default', 'id_documento' => '5', 'documento_formatado' => '0000005']);
    $sync = Sei::sincronizador();

    $sync->processo($processo);
    Event::assertNotDispatched(DocumentoSeiAssinado::class);

    $sync->processo($processo);
    Event::assertDispatched(DocumentoSeiAssinado::class, fn ($e) => count($e->novas) === 1 && $e->novas[0]->sigla === 'ana');

    $sync->processo($processo);
    Event::assertDispatched(DocumentoSeiAssinado::class, 1);
    Event::assertDispatched(PublicacaoSeiAgendada::class);

    $sync->processo($processo);
    Event::assertDispatched(DocumentoSeiPublicado::class);

    expect($documento->refresh())
        ->assinado()->toBeTrue()
        ->assinado_em->format('Y-m-d H:i')->toBe('2026-12-03 10:00')
        ->publicacao_estado->toBe('P')
        ->publicado_em->format('Y-m-d')->toBe('2026-12-05');
});

it('desliga a sincronização quando o SEI recusa a consulta', function () {
    Event::fake(EVENTOS_SINCRONIZACAO);
    Sei::fake()->respond('consultarProcedimento', new AccessDeniedException('Acesso negado ao CPF 052.559.530-91', 'consultarProcedimento'));
    $processo = processoVinculado();

    Sei::sincronizador()->processo($processo);

    Event::assertDispatched(SincronizacaoSeiFalhou::class);
    expect($processo->refresh())
        ->sincronizar->toBeFalse()
        ->erro_sincronizacao->toBe('Acesso negado ao CPF ***.***.***-91');
});

it('usa a conexão de leitura configurada', function () {
    config(['sei.sincronizacao.conexao' => ['default' => 'orgao-b']]);
    $fake = Sei::fake()->respond('consultarProcedimento', consultaProcesso(['1']));

    Sei::sincronizador()->processo(processoVinculado(['id_unidade_geradora' => null]));

    $fake->assertCalled('consultarProcedimento', fn ($a) => $a['SiglaSistema'] === 'OUTRO' && $a['IdUnidade'] === '220000002');
});

it('sei:sincronizar enfileira só os processos vencidos', function () {
    Bus::fake();
    $vencido = processoVinculado(['proxima_sincronizacao_em' => now()->subMinute()]);
    processoVinculado(['protocolo_formatado' => 'FUTURO', 'proxima_sincronizacao_em' => now()->addHour()]);
    processoVinculado(['protocolo_formatado' => 'DESLIGADO', 'sincronizar' => false]);
    processoVinculado(['protocolo_formatado' => 'NOVO']);

    $this->artisan('sei:sincronizar')->expectsOutputToContain('2 processo(s) enfileirado(s)')->assertSuccessful();

    Bus::assertDispatchedTimes(SincronizarProcessoSeiJob::class, 2);
    Bus::assertDispatched(SincronizarProcessoSeiJob::class, fn ($job) => $job->processo->is($vencido));
});

it('sei:sincronizar --agora sincroniza um processo específico', function () {
    $fake = Sei::fake()->respond('consultarProcedimento', consultaProcesso(['1']));
    processoVinculado(['proxima_sincronizacao_em' => now()->addHour()]);

    $this->artisan('sei:sincronizar', ['--processo' => '12.1.000000077-4', '--agora' => true])
        ->expectsOutputToContain('1 processo(s) sincronizado(s)')
        ->assertSuccessful();

    $fake->assertCalledTimes('consultarProcedimento', 1);
});

it('agenda a sincronização quando configurado', function () {
    config(['sei.sincronizacao.agendar' => true]);
    (new SeiServiceProvider($this->app))->boot();

    $eventos = collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains($e->command ?? '', 'sei:sincronizar'));

    expect($eventos)->toHaveCount(1)
        ->and($eventos->first()->expression)->toBe('*/15 * * * *');
});

function andamentoSoap(string $id, string $tarefa = '65'): object
{
    return (object) ['IdAndamento' => $id, 'IdTarefa' => $tarefa, 'DataHora' => '01/12/2026 10:00:00'];
}

it('dispara os andamentos intermediários das tarefas configuradas, em ordem e antes do último', function () {
    config(['sei.sincronizacao.andamentos' => ['tarefas' => [65, 1000], 'atributos' => true]]);
    $eventos = [];
    Event::listen(NovoAndamentoSei::class, function (NovoAndamentoSei $e) use (&$eventos): void {
        $eventos[] = [$e->andamento->idAndamento, $e->intermediario];
    });
    $fake = Sei::fake()
        ->respond('consultarProcedimento', consultaProcesso(['1'], '98'), consultaProcesso(['1'], '1000'))
        ->respond('listarAndamentos', [
            andamentoSoap('1000'),  // o último: não repete
            andamentoSoap('999', '1000'),
            andamentoSoap('98'),    // já conhecido
            andamentoSoap('120'),
            andamentoSoap('97'),    // anterior
        ]);
    $processo = processoVinculado();
    $sync = Sei::sincronizador();

    $sync->processo($processo);
    $fake->assertNotCalled('listarAndamentos');
    $sync->processo($processo);

    expect($eventos)->toBe([['120', true], ['999', true], ['1000', false]]);
    $fake->assertCalled('listarAndamentos', fn ($a) => $a['ProtocoloProcedimento'] === '12.1.000000077-4'
        && $a['Tarefas'] === ['65', '1000'] && $a['SinRetornarAtributos'] === 'S');
});

it('sem tarefas configuradas não lista andamentos', function () {
    Event::fake(EVENTOS_SINCRONIZACAO);
    $fake = Sei::fake()->respond('consultarProcedimento', consultaProcesso(['1'], '10'), consultaProcesso(['1'], '11'));
    $processo = processoVinculado();

    Sei::sincronizador()->processo($processo);
    Sei::sincronizador()->processo($processo);

    $fake->assertNotCalled('listarAndamentos');
    Event::assertDispatchedTimes(NovoAndamentoSei::class, 1);
});

it('sem a operação liberada ou com erro do SEI, vale só o último andamento', function () {
    config(['sei.sincronizacao.andamentos.tarefas' => [65]]);
    Event::fake(EVENTOS_SINCRONIZACAO);
    $fake = Sei::fake()
        ->respond('consultarProcedimento', consultaProcesso(['1'], '10'), consultaProcesso(['1'], '11'), consultaProcesso(['1'], '12'))
        ->respond('listarAndamentos', new FaultException('Processo não encontrado.', 'listarAndamentos'));
    $processo = processoVinculado();
    $sync = Sei::sincronizador();

    $sync->processo($processo);
    $sync->processo($processo);
    expect($processo->refresh()->sincronizar)->toBeTrue();

    config(['sei.connections.default.operacoes' => ['consultar*']]);
    app(SeiManager::class)->purge();
    Sei::sincronizador()->processo($processo);

    $fake->assertCalledTimes('listarAndamentos', 1);
    Event::assertDispatchedTimes(NovoAndamentoSei::class, 2);
    Event::assertNotDispatched(NovoAndamentoSei::class, fn ($e) => $e->intermediario);
});

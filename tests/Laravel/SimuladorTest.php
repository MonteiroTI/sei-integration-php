<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use MonteiroTi\Sei\Dto\Documento;
use MonteiroTi\Sei\Dto\Procedimento;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\DocumentoSeiAssinado;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\NovoAndamentoSei;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\ProcessoSeiConcluido;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\ProcessoSeiTramitado;
use MonteiroTi\Sei\Laravel\Facades\Sei;
use MonteiroTi\Sei\Tests\Support\Requerimento;
use PHPUnit\Framework\AssertionFailedError;

it('percorre gerar, tramitar, assinar e concluir só com o SEI simulado', function () {
    Event::fake([NovoAndamentoSei::class, ProcessoSeiTramitado::class, ProcessoSeiConcluido::class, DocumentoSeiAssinado::class]);
    $fake = Sei::fake()->simular();
    $requerimento = Requerimento::create(['assunto' => 'Férias']);

    $processo = Sei::integracao()->gerarProcesso(
        $requerimento,
        new Procedimento('100000368', 'Requerimento de férias'),
        [Documento::gerado('3', conteudo: '<p>Requerimento</p>')],
    );
    $protocolo = $processo->protocolo_formatado;
    $sync = Sei::sincronizador();
    $sync->processo($processo);   // estado inicial

    $fake->sei()->tramitar($protocolo, '110000015');
    $fake->sei()->assinar('0000001', 'Maria Souza');
    $sync->processo($processo->refresh());

    Event::assertDispatched(ProcessoSeiTramitado::class, fn ($e) => $e->entrou() === ['110000015'] && $e->saiu() === ['110000001']);
    Event::assertDispatched(NovoAndamentoSei::class);
    Event::assertDispatched(DocumentoSeiAssinado::class, fn ($e) => $e->novas[0]->sigla === 'maria');

    $fake->sei()->concluir($protocolo);
    $sync->processo($processo->refresh());

    Event::assertDispatched(ProcessoSeiConcluido::class);
    expect($processo->refresh()->aberto)->toBeFalse()
        ->and($requerimento->seiProcesso?->documentos)->toHaveCount(1)
        ->and($fake->sei()->processo($protocolo)['especificacao'])->toBe('Requerimento de férias');

    $fake->assertCalledInOrder(['gerarProcedimento', 'consultarProcedimento', 'consultarDocumento', 'consultarProcedimento']);
});

it('assertCalledInOrder falha quando a ordem não confere', function () {
    $fake = Sei::fake()->simular();
    Sei::processos()->gerar(new Procedimento('1'));
    Sei::processos()->consultar('12.1.000000001-1');

    expect(fn () => $fake->assertCalledInOrder(['consultarProcedimento', 'gerarProcedimento']))
        ->toThrow(AssertionFailedError::class);

    $fake->transport()->reset();
    expect(fn () => $fake->sei())->toThrow(LogicException::class);
});

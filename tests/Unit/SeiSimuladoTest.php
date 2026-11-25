<?php

declare(strict_types=1);

use MonteiroTi\Sei\Core\Exceptions\FaultException;
use MonteiroTi\Sei\Core\Exceptions\SeiException;
use MonteiroTi\Sei\Dto\ContatoRef;
use MonteiroTi\Sei\Dto\Documento;
use MonteiroTi\Sei\Dto\Procedimento;
use MonteiroTi\Sei\Enum\NivelAcesso;
use MonteiroTi\Sei\Options\ConsultaDocumentoOptions;
use MonteiroTi\Sei\Options\ConsultaProcedimentoOptions;
use MonteiroTi\Sei\Options\GerarProcedimentoOptions;
use MonteiroTi\Sei\Testing\Respostas;
use MonteiroTi\Sei\Testing\SeiSimulado;

it('gera processos com documentos, protocolos sequenciais e consulta coerente', function () {
    $sei = fakeSei($transport);
    $simulado = $transport->simular();

    $ret = $sei->processos()->gerar(
        new Procedimento('100000368', 'Férias'),
        [Documento::gerado('3', conteudo: '<p>x</p>'), Documento::externo('301', new DateTimeImmutable, 'a.pdf', ContatoRef::nome('Paulo'), conteudo: 'pdf')],
    );
    $outro = $sei->processos()->gerar(new Procedimento('100000368', 'Outro'));

    expect($ret->procedimentoFormatado)->toBe('12.1.000000001-1')
        ->and($outro->procedimentoFormatado)->toBe('12.1.000000002-2')
        ->and(array_map(fn ($d) => $d->documentoFormatado, $ret->documentos))->toBe(['0000001', '0000002']);

    $consulta = $sei->processos()->consultar($ret->procedimentoFormatado, ConsultaProcedimentoOptions::completo());
    expect($consulta->especificacao)->toBe('Férias')
        ->and($consulta->idProcedimento)->toBe($ret->idProcedimento)
        ->and($consulta->tipoProcedimento?->idTipoProcedimento)->toBe('100000368')
        ->and(array_map(fn ($u) => $u->unidade?->idUnidade, $consulta->unidadesProcedimentoAberto))->toBe(['110000001'])
        ->and($consulta->andamentoGeracao?->idTarefa)->toBe((string) SeiSimulado::TAREFA_GERACAO)
        ->and($consulta->ultimoAndamento?->descricao)->toBe('Gerado documento 0000002')
        ->and($simulado->processo($ret->procedimentoFormatado)['especificacao'])->toBe('Férias');

    $doc = $sei->documentos()->consultar('0000001');
    expect($doc->procedimentoFormatado)->toBe('12.1.000000001-1')->and($doc->serie?->idSerie)->toBe('3');
});

it('gera e envia para outras unidades, mantendo ou não aberto na geradora', function () {
    $sei = fakeSei($transport);
    $transport->simular();

    $p1 = $sei->processos()->gerar(new Procedimento('1'), options: new GerarProcedimentoOptions(unidadesEnvio: ['110000015']));
    $p2 = $sei->processos()->gerar(new Procedimento('1'), options: new GerarProcedimentoOptions(unidadesEnvio: ['110000015'], manterAbertoUnidade: true));

    $abertas = fn (string $p) => array_map(fn ($u) => $u->unidade?->idUnidade, $sei->processos()->consultar($p, new ConsultaProcedimentoOptions(unidadesProcedimentoAberto: true))->unidadesProcedimentoAberto);
    expect($abertas($p1->procedimentoFormatado))->toBe(['110000015'])
        ->and($abertas($p2->procedimentoFormatado))->toBe(['110000001', '110000015']);
});

it('tramita, conclui e reabre pelos métodos do WebService', function () {
    $sei = fakeSei($transport);
    $transport->simular();
    $p = $sei->processos()->gerar(new Procedimento('1'))->procedimentoFormatado;
    $opcoes = new ConsultaProcedimentoOptions(andamentoConclusao: true, unidadesProcedimentoAberto: true);

    $sei->processos()->enviar($p, ['110000015']);
    $sei->naUnidade('110000015')->processos()->concluir($p);
    $concluido = $sei->processos()->consultar($p, $opcoes);

    expect($concluido->aberto())->toBeFalse()
        ->and($concluido->andamentoConclusao?->idTarefa)->toBe((string) SeiSimulado::TAREFA_CONCLUSAO);

    $sei->processos()->reabrir($p);
    $reaberto = $sei->processos()->consultar($p, $opcoes);
    expect($reaberto->aberto())->toBeTrue()->and($reaberto->andamentoConclusao)->toBeNull();
});

it('lança e lista andamentos por tarefa ou ID, com atributos opcionais', function () {
    $sei = fakeSei($transport);
    $transport->simular();
    $p = $sei->processos()->gerar(new Procedimento('1'))->procedimentoFormatado;

    $lancado = $sei->andamentos()->atualizar($p, 'Pagamento confirmado');
    $sei->andamentos()->lancar($p, 1000);

    $com = $sei->andamentos()->listar($p, tarefas: [65], retornarAtributos: true);
    $sem = $sei->andamentos()->listar($p, andamentos: [(string) $lancado->idAndamento]);
    $modulo = $sei->andamentos()->listar($p, tarefas: [1000]);

    expect($lancado->descricao)->toBe('Pagamento confirmado')
        ->and($com)->toHaveCount(1)
        ->and($com[0]->atributo('DESCRICAO'))->toBe('Pagamento confirmado')
        ->and($sem[0]->atributos)->toBe([])
        ->and($modulo)->toHaveCount(1);
});

it('assina documentos e recebe arquivos em partes', function () {
    $sei = fakeSei($transport, chunk: 4);
    $simulado = $transport->simular();
    $ret = $sei->processos()->gerar(new Procedimento('1'), [Documento::gerado('3', conteudo: 'x')]);

    $simulado->assinar('0000001', 'Maria Souza')->assinar('0000001', 'João Lima');
    $doc = $sei->documentos()->consultar('0000001', new ConsultaDocumentoOptions(assinaturas: true));

    $stream = fopen('php://memory', 'r+');
    fwrite($stream, 'conteúdo do arquivo');
    rewind($stream);
    $idArquivo = $sei->documentos()->enviarArquivo($stream, 'a.txt');

    expect(array_map(fn ($a) => $a->sigla, $doc->assinaturas))->toBe(['maria', 'joão'])
        ->and($simulado->arquivo($idArquivo))->toBe('conteúdo do arquivo')
        ->and($ret->documentos)->toHaveCount(1);
});

it('respostas programadas têm prioridade e operações não simuladas pedem respond()', function () {
    $sei = fakeSei($transport);
    $transport->simular();
    $transport->respond('gerarProcedimento', Respostas::processoGerado('99.9.000000001-1'));

    expect($sei->processos()->gerar(new Procedimento('1'))->procedimentoFormatado)->toBe('99.9.000000001-1')
        ->and(fn () => $sei->processos()->bloquear('P'))->toThrow(SeiException::class, 'não é simulada')
        ->and(fn () => $sei->processos()->consultar('12.1.000000404-4'))->toThrow(FaultException::class, 'não encontrado');
});

it('não devolve processos sigilosos nas consultas', function () {
    $sei = fakeSei($transport);
    $transport->simular();
    $p = $sei->processos()->gerar(new Procedimento('1', nivelAcesso: NivelAcesso::Sigiloso, idHipoteseLegal: '1'))->procedimentoFormatado;

    expect(fn () => $sei->processos()->consultar($p))->toThrow(FaultException::class);
});

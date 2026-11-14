<?php

declare(strict_types=1);

use MonteiroTi\Sei\Core\Exceptions\InvalidArgumentException;
use MonteiroTi\Sei\Dto\Contato;
use MonteiroTi\Sei\Enum\EstadoBloco;
use MonteiroTi\Sei\Enum\MotivoPublicacao;
use MonteiroTi\Sei\Enum\Natureza;
use MonteiroTi\Sei\Enum\OperacaoContato;
use MonteiroTi\Sei\Enum\TipoBloco;
use MonteiroTi\Sei\Options\DadosPublicacao;
use MonteiroTi\Sei\Options\FiltroContatos;
use MonteiroTi\Sei\Options\ManifestacaoOuvidoria;

it('valida e lança andamentos', function () {
    $sei = fakeSei($t);
    $t->respond('lancarAndamento', (object) ['IdAndamento' => '1', 'IdTarefa' => '65']);

    expect(fn () => $sei->andamentos()->lancar('P', 64))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $sei->andamentos()->lancar('P'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $sei->andamentos()->listar('P'))->toThrow(InvalidArgumentException::class);

    $andamento = $sei->andamentos()->atualizar('P', 'Recebido pelo sistema X');

    expect($andamento->idAndamento)->toBe('1')
        ->and($t->lastArguments('lancarAndamento'))->toMatchArray([
            'IdTarefa' => '65',
            'Atributos' => [['Nome' => 'DESCRICAO', 'Valor' => 'Recebido pelo sistema X', 'IdOrigem' => null]],
        ]);
});

it('gera e consulta blocos', function () {
    $sei = fakeSei($t);
    $t->respond('gerarBloco', '4321')->respond('consultarBloco', (object) [
        'IdBloco' => '4321', 'Tipo' => 'A', 'Estado' => 'D',
        'Protocolos' => [(object) ['ProtocoloFormatado' => '0003934', 'Assinaturas' => (object) ['Nome' => 'Fulano']]],
    ]);

    $id = $sei->blocos()->gerar(TipoBloco::Assinatura, 'Assinar', ['110000015'], ['0003934'], true);
    $bloco = $sei->blocos()->consultar($id, true);

    expect($id)->toBe('4321')
        ->and($t->lastArguments('gerarBloco'))->toMatchArray(['Tipo' => 'A', 'Documentos' => ['0003934'], 'SinDisponibilizar' => 'S'])
        ->and($bloco->estado)->toBe(EstadoBloco::Disponibilizado)
        ->and($bloco->protocolos[0]->assinaturas[0]->nome)->toBe('Fulano');
});

it('exige um único identificador de documento nas publicações', function () {
    $sei = fakeSei($t);
    $t->respond('agendarPublicacao', '88');
    $dados = new DadosPublicacao(MotivoPublicacao::Publicacao, '3', new DateTimeImmutable('2026-12-01'), 'Resumo');

    expect(fn () => $sei->publicacoes()->agendar($dados))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $sei->publicacoes()->agendar($dados, '0003934', '1140000000872'))->toThrow(InvalidArgumentException::class)
        ->and($sei->publicacoes()->agendar($dados, protocoloDocumento: '0003934'))->toBe('88')
        ->and($t->lastArguments('agendarPublicacao'))->toMatchArray([
            'IdDocumento' => null, 'ProtocoloDocumento' => '0003934', 'StaMotivo' => 1, 'DataDisponibilizacao' => '01/12/2026',
        ]);
});

it('confirma disponibilização sem IdUnidade', function () {
    $sei = fakeSei($t);
    $t->respond('confirmarDisponibilizacaoPublicacao', true);

    $sei->publicacoes()->confirmarDisponibilizacao('3', new DateTimeImmutable('2026-12-01'), new DateTimeImmutable('2026-12-02'), '123', ['1']);

    expect($t->lastArguments('confirmarDisponibilizacaoPublicacao'))->not->toHaveKey('IdUnidade');
});

it('pagina contatos até a última página', function () {
    $sei = fakeSei($t);
    $pagina = fn (int $n) => array_map(fn ($i) => (object) ['IdContato' => (string) $i, 'Nome' => "C{$i}", 'StaNatureza' => 'F'], range(1, $n));
    $t->respond('listarContatos', $pagina(2), $pagina(2), $pagina(1));

    $todos = iterator_to_array($sei->contatos()->todos(new FiltroContatos(nome: 'C'), 2), false);

    expect($todos)->toHaveCount(5)
        ->and($todos[0]->natureza)->toBe(Natureza::PessoaFisica)
        ->and(array_column(array_column($t->calls('listarContatos'), 'arguments'), 'PaginaAtual'))->toBe([1, 2, 3])
        ->and(fn () => $sei->contatos()->listar(porPagina: 1001))->toThrow(InvalidArgumentException::class);
});

it('atualiza contatos com todos os campos da estrutura', function () {
    $sei = fakeSei($t);
    $t->respond('atualizarContatos', true);

    $contato = new Contato(operacao: OperacaoContato::Atualizar, nome: 'Fulano', natureza: Natureza::PessoaFisica, cpf: '1', ativo: true);
    $sei->contatos()->atualizar($contato, $contato->comOperacao(OperacaoContato::Desativar));

    $enviados = $t->lastArguments('atualizarContatos')['Contatos'];
    expect($enviados)->toHaveCount(2)
        ->and($enviados[0])->toHaveCount(43)
        ->and($enviados[0])->toMatchArray(['StaOperacao' => 'A', 'StaNatureza' => 'F', 'SinAtivo' => 'S'])
        ->and($enviados[1]['StaOperacao'])->toBe('D');
});

it('registra ouvidoria', function () {
    $sei = fakeSei($t, idUnidade: null);
    $t->respond('registrarOuvidoria', (object) ['IdProcedimento' => '1', 'ProcedimentoFormatado' => '12.1.000000001-1']);

    expect(fn () => new ManifestacaoOuvidoria('0', '1', 'Nome', 'e@x', 'msg'))->toThrow(InvalidArgumentException::class);

    $ret = $sei->ouvidoria()->registrar(new ManifestacaoOuvidoria('0', '1', 'Nome', 'e@x', 'msg', cpf: '123', retorno: true));

    expect($ret->procedimentoFormatado)->toBe('12.1.000000001-1')
        ->and($t->lastArguments('registrarOuvidoria'))->toMatchArray(['SinRetorno' => 'S', 'SinAnonimo' => 'N', 'Anexos' => []]);
});

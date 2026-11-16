<?php

declare(strict_types=1);

use MonteiroTi\Sei\Core\Config\SeiConfig;
use MonteiroTi\Sei\Core\Exceptions\AccessDeniedException;
use MonteiroTi\Sei\Core\Transport\FakeTransport;
use MonteiroTi\Sei\SeiClient;

function clienteCom(array $config, ?FakeTransport &$t = null): SeiClient
{
    $t = new FakeTransport;

    return new SeiClient($t, SeiConfig::fromArray(['sigla_sistema' => 'S', 'identificacao_servico' => 'K', 'id_unidade' => '1', ...$config]));
}

it('bloqueia localmente operações fora da lista, com curinga', function () {
    $sei = clienteCom(['operacoes' => ['consultar*', 'listarUnidades', 'lancarAndamento']], $t);
    $t->respond('listarUnidades', [])->respond('consultarProcedimento', (object) []);

    $sei->catalogos()->unidades();
    $sei->processos()->consultar('P');

    expect(fn () => $sei->processos()->concluir('P'))->toThrow(AccessDeniedException::class, 'concluirProcesso')
        ->and(fn () => $sei->catalogos()->series())->toThrow(AccessDeniedException::class)
        ->and($t->calls('concluirProcesso'))->toBe([])
        ->and($sei->operacoesPermitidas())->toContain('consultarDocumento', 'listarUnidades', 'lancarAndamento')
        ->and($sei->operacoesPermitidas())->not->toContain('gerarProcedimento');
});

it('somente_leitura libera apenas consultar* e listar*', function () {
    $sei = clienteCom(['somente_leitura' => true]);

    expect($sei->operacoesPermitidas())->each->toMatch('/^(consultar|listar)/')
        ->and(fn () => $sei->processos()->excluir('P'))->toThrow(AccessDeniedException::class)
        ->and($sei->naUnidade('2')->config()->somenteLeitura)->toBeTrue();
});

it('combina somente_leitura com a lista de operações', function () {
    $config = SeiConfig::fromArray(['sigla_sistema' => 'S', 'identificacao_servico' => 'K', 'somente_leitura' => true, 'operacoes' => ['listar*', 'gerarProcedimento']]);

    expect($config->permite('listarSeries'))->toBeTrue()
        ->and($config->permite('consultarProcedimento'))->toBeFalse()
        ->and($config->permite('gerarProcedimento'))->toBeFalse();
});

it('sem restrições, todas as operações ficam liberadas', function () {
    expect(clienteCom([])->operacoesPermitidas())->toHaveCount(66);
});

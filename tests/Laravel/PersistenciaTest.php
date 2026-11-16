<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use MonteiroTi\Sei\Dto\RetornoGeracaoProcedimento;
use MonteiroTi\Sei\Dto\RetornoInclusaoDocumento;
use MonteiroTi\Sei\Laravel\Facades\Sei;
use MonteiroTi\Sei\Laravel\Models\SeiDocumento;
use MonteiroTi\Sei\Laravel\Models\SeiOperacao;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;
use MonteiroTi\Sei\Laravel\Models\StatusOperacao;
use MonteiroTi\Sei\Tests\Support\Requerimento;

it('vincula processos e documentos a models do sistema', function () {
    $req = Requerimento::create(['assunto' => 'Férias']);

    $processo = $req->seiProcessos()->create([
        'conexao' => 'default',
        'id_procedimento' => '1210000000774',
        'protocolo_formatado' => '12.1.000000077-4',
        'unidades_abertas' => [['id' => '110000001', 'sigla' => 'PROTOCOLO']],
    ]);
    $processo->documentos()->create(['conexao' => 'default', 'id_documento' => '1', 'documento_formatado' => '0003934', 'tipo' => 'G']);

    $req->refresh();

    expect($req->temProcessoSei())->toBeTrue()
        ->and($req->temProcessoSei('orgao-b'))->toBeFalse()
        ->and($req->seiProcesso->protocolo_formatado)->toBe('12.1.000000077-4')
        ->and($req->seiProcesso->idsUnidadesAbertas())->toBe(['110000001'])
        ->and($req->seiProcesso->aberto)->toBeTrue()
        ->and($req->seiProcesso->documentos)->toHaveCount(1)
        ->and(SeiDocumento::first()->processo->vinculavel->is($req))->toBeTrue()
        ->and((new SeiProcesso)->getTable())->toBe('sei_processos');
});

it('guarda e restaura resultados de operações só com classes da biblioteca', function () {
    $op = new SeiOperacao(['chave' => 'k', 'conexao' => 'default', 'operacao' => 'gerarProcedimento', 'status' => StatusOperacao::Concluida]);
    $op->guardarResultado(new RetornoGeracaoProcedimento('1', '12.1.000000077-4', 'l', [new RetornoInclusaoDocumento('2', '0003934')]));
    $op->save();

    $lido = SeiOperacao::where('chave', 'k')->first();

    expect($lido->status)->toBe(StatusOperacao::Concluida)
        ->and($lido->lerResultado())->toBeInstanceOf(RetornoGeracaoProcedimento::class)
        ->and($lido->lerResultado()->documentos[0]->documentoFormatado)->toBe('0003934');

    $lido->resultado = serialize(new ArrayObject([1]));
    expect($lido->lerResultado())->toBeInstanceOf(__PHP_Incomplete_Class::class);
});

it('lê parâmetros por nome', function () {
    expect(Sei::parametro('tipos_processo.ferias'))->toBe('100000368')
        ->and(fn () => Sei::parametro('series.portaria'))->toThrow(InvalidArgumentException::class, 'series.portaria');
});

it('sei:parametros:validar aponta IDs inexistentes', function () {
    Sei::fake()
        ->respond('listarUnidades', [(object) ['IdUnidade' => '110000001', 'Sigla' => 'PROTOCOLO']])
        ->respond('listarTiposProcedimento', [(object) ['IdTipoProcedimento' => '100000368', 'Nome' => 'Férias']]);

    $this->artisan('sei:parametros:validar')
        ->expectsOutputToContain('ok — PROTOCOLO')
        ->expectsOutputToContain('NÃO ENCONTRADO')
        ->expectsOutputToContain('não verificado')
        ->assertFailed();
});

it('sei:catalogo --php gera o trecho de parâmetros', function () {
    Sei::fake()->respond('listarTiposProcedimento', [
        (object) ['IdTipoProcedimento' => '100000368', 'Nome' => 'Pessoal: Férias'],
        (object) ['IdTipoProcedimento' => '100000369', 'Nome' => 'Pessoal: Férias'],
    ]);

    $this->artisan('sei:catalogo', ['tipo' => 'tipos-processo', '--php' => true])
        ->expectsOutputToContain("'pessoal_ferias' => '100000368', // Pessoal: Férias")
        ->expectsOutputToContain("'pessoal_ferias_2' => '100000369'")
        ->assertSuccessful();
});

it('publica a migration', function () {
    $paths = ServiceProvider::pathsToPublish(null, 'sei-migrations');

    expect($paths)->toHaveCount(1)
        ->and(array_key_first($paths))->toEndWith('create_sei_tables.php.stub')
        ->and(reset($paths))->toEndWith('_create_sei_tables.php');
});

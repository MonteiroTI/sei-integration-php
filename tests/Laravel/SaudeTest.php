<?php

declare(strict_types=1);

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTi\Sei\Laravel\SeiServiceProvider;

/** Rota equivalente à /up do Laravel (DiagnosingHealth; exceção vira 500). */
function rotaUp(): void
{
    Route::get('/up', function () {
        try {
            Event::dispatch(new DiagnosingHealth);
        } catch (Throwable $e) {
            return response($e->getMessage(), 500);
        }

        return response('ok');
    });
}

function abrirCircuito(string $conexao = 'default'): void
{
    $chave = "sei:{$conexao}";
    app('cache')->store()->put("sei:circuito:{$chave}", ['falhas' => 5, 'desde' => time(), 'abertoAte' => time() + 30], 60);
}

beforeEach(function () {
    config(['app.debug' => false]);
    rotaUp();
});

it('fica desligado por padrão', function () {
    abrirCircuito();

    expect(Event::hasListeners(DiagnosingHealth::class))->toBeFalse();
    $this->get('/up')->assertOk();
});

it('o /up responde 200 com o circuito fechado e 500 com ele aberto', function () {
    config(['sei.saude.up' => true]);
    (new SeiServiceProvider($this->app))->boot();

    $this->get('/up')->assertOk();

    abrirCircuito();
    $this->get('/up')->assertStatus(500)->assertSee('circuito aberto na(s) conexão(ões) default');

    app(SeiManager::class)->connection()->circuito()?->fechar();
    $this->get('/up')->assertOk();
});

it('monitora só as conexões configuradas', function () {
    config(['sei.saude.up' => true, 'sei.saude.conexoes' => ['orgao-b']]);
    (new SeiServiceProvider($this->app))->boot();

    abrirCircuito('default');
    $this->get('/up')->assertOk();

    abrirCircuito('orgao-b');
    $this->get('/up')->assertStatus(500)->assertSee('orgao-b');
});

it('sei:ping avisa quando o circuito está aberto', function () {
    abrirCircuito();

    $this->artisan('sei:ping')
        ->expectsOutputToContain('Circuito aberto até')
        ->assertFailed();
});

<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\ServiceProvider;
use MonteiroTi\Sei\Laravel\Console\CatalogoCommand;
use MonteiroTi\Sei\Laravel\Console\OperacoesCommand;
use MonteiroTi\Sei\Laravel\Console\PingCommand;
use MonteiroTi\Sei\Laravel\Console\ResolverOperacaoCommand;
use MonteiroTi\Sei\Laravel\Console\SincronizarCommand;
use MonteiroTi\Sei\Laravel\Console\ValidarParametrosCommand;
use MonteiroTi\Sei\Laravel\Console\WsdlCommand;
use MonteiroTi\Sei\Laravel\Saude\VerificarCircuitos;
use MonteiroTi\Sei\SeiClient;
use MonteiroTi\Sei\Sip\SipClient;

final class SeiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/sei.php', 'sei');

        $this->app->singleton(SeiManager::class, fn (Container $app) => new SeiManager($app));
        $this->app->alias(SeiManager::class, 'sei');

        $this->app->singleton(SipManager::class, fn (Container $app) => new SipManager($app->make(SeiManager::class)));
        $this->app->alias(SipManager::class, 'sei.sip');

        // Injeção direta da conexão padrão: public function __construct(SeiClient $sei)
        $this->app->bind(SeiClient::class, fn (Container $app) => $app->make(SeiManager::class)->connection());
        $this->app->bind(SipClient::class, fn (Container $app) => $app->make(SeiManager::class)->sip());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/sei.php' => $this->app->configPath('sei.php'),
            ], 'sei-config');

            $this->publishesMigrations([
                __DIR__.'/../../database/migrations/create_sei_tables.php.stub' => $this->app->databasePath('migrations/'.date('Y_m_d_His').'_create_sei_tables.php'),
            ], 'sei-migrations');

            // Guia para o Claude Code integrar o pacote no sistema (skill carregada sob demanda).
            $this->publishes([
                __DIR__.'/../../resources/claude/skills/sei-integration/SKILL.md' => $this->app->basePath('.claude/skills/sei-integration/SKILL.md'),
            ], 'sei-claude');

            $this->commands([
                PingCommand::class,
                CatalogoCommand::class,
                ValidarParametrosCommand::class,
                OperacoesCommand::class,
                ResolverOperacaoCommand::class,
                SincronizarCommand::class,
                WsdlCommand::class,
            ]);
        }

        // Health check: a rota /up do Laravel falha quando o circuito de uma conexão monitorada está aberto.
        if (config('sei.saude.up', false) && class_exists(DiagnosingHealth::class)) {
            $this->app->make('events')->listen(DiagnosingHealth::class, VerificarCircuitos::class);
        }

        if (config('sei.sincronizacao.agendar', false)) {
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->command('sei:sincronizar')
                    ->cron((string) config('sei.sincronizacao.cron', '*/15 * * * *'))
                    ->withoutOverlapping()
                    ->runInBackground();
            });
        }
    }

    /**
     * @return list<string>
     */
    public function provides(): array
    {
        return [SeiManager::class, 'sei', SipManager::class, 'sei.sip', SeiClient::class, SipClient::class];
    }
}

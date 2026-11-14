<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use MonteiroTi\Sei\Laravel\Console\CatalogoCommand;
use MonteiroTi\Sei\Laravel\Console\PingCommand;
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

            $this->commands([PingCommand::class, CatalogoCommand::class]);
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

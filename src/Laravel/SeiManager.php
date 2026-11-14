<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel;

use Illuminate\Contracts\Container\Container;
use MonteiroTi\Sei\Core\Config\SeiConfig;
use MonteiroTi\Sei\Core\Config\SipConfig;
use MonteiroTi\Sei\Core\Config\TransportOptions;
use MonteiroTi\Sei\Core\Support\CatalogCache;
use MonteiroTi\Sei\Core\Transport\FakeTransport;
use MonteiroTi\Sei\Core\Transport\SoapTransport;
use MonteiroTi\Sei\Core\Transport\Transport;
use MonteiroTi\Sei\Laravel\Testing\SeiFake;
use MonteiroTi\Sei\SeiClient;
use MonteiroTi\Sei\Sip\SipClient;

/**
 * Resolve clientes SEI e SIP por conexão nomeada (config/sei.php).
 *
 * @mixin SeiClient
 */
class SeiManager
{
    /** @var array<string, SeiClient> */
    private array $clients = [];

    /** @var array<string, SipClient> */
    private array $sipClients = [];

    private ?FakeTransport $fake = null;

    public function __construct(private readonly Container $app) {}

    public function connection(?string $name = null): SeiClient
    {
        $name ??= $this->getDefaultConnection();

        return $this->clients[$name] ??= $this->makeSei($name);
    }

    public function sip(?string $name = null): SipClient
    {
        $name ??= (string) $this->config('sip.default', 'default');

        return $this->sipClients[$name] ??= $this->makeSip($name);
    }

    public function getDefaultConnection(): string
    {
        return (string) $this->config('default', 'default');
    }

    /**
     * Substitui o transporte de todas as conexões (SEI e SIP) por um FakeTransport.
     */
    public function fake(): SeiFake
    {
        $fake = $this->fake ??= new FakeTransport;
        $this->purge();

        return new SeiFake($fake);
    }

    public function isFake(): bool
    {
        return $this->fake !== null;
    }

    /** Descarta os clientes já criados (ex.: após alterar a configuração em runtime). */
    public function purge(?string $name = null): void
    {
        if ($name === null) {
            $this->clients = $this->sipClients = [];

            return;
        }
        unset($this->clients[$name], $this->sipClients[$name]);
    }

    /**
     * @return array<string, mixed>
     */
    public function connectionConfig(string $name): array
    {
        $config = $this->config("connections.{$name}");
        if (! is_array($config)) {
            throw new \InvalidArgumentException("Conexão SEI [{$name}] não configurada em config/sei.php.");
        }

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    public function sipConnectionConfig(string $name): array
    {
        $config = $this->config("sip.connections.{$name}");
        if (! is_array($config)) {
            throw new \InvalidArgumentException("Conexão SIP [{$name}] não configurada em config/sei.php.");
        }

        return $config;
    }

    private function makeSei(string $name): SeiClient
    {
        $config = $this->connectionConfig($name);

        return new SeiClient(
            $this->transport("sei:{$name}", $config),
            SeiConfig::fromArray($config),
            $this->catalogCache($name),
        );
    }

    private function makeSip(string $name): SipClient
    {
        $config = $this->sipConnectionConfig($name);

        return new SipClient($this->transport("sip:{$name}", $config), SipConfig::fromArray($config));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function transport(string $connection, array $config): Transport
    {
        $transport = $this->fake ?? new SoapTransport(TransportOptions::fromArray($config));

        $events = $this->config('events', true) ? $this->app->make('events') : null;
        $logger = null;
        if ($this->config('log.enabled', false)) {
            $logger = $this->app->make('log')->channel($this->config('log.channel'));
        }

        if ($events === null && $logger === null) {
            return $transport;
        }

        return new ObservedTransport($transport, $connection, $events, $logger);
    }

    private function catalogCache(string $name): ?CatalogCache
    {
        if (! $this->config('cache.enabled', true) || $this->fake !== null) {
            return null;
        }

        $store = $this->app->make('cache')->store($this->config('cache.store'));

        return new LaravelCatalogCache(
            $store,
            (int) $this->config('cache.ttl', 86400),
            $this->config('cache.prefix', 'sei').':'.$name,
        );
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return $this->app->make('config')->get("sei.{$key}", $default);
    }

    /**
     * @param  array<int, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->connection()->{$method}(...$parameters);
    }
}

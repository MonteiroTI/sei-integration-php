<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
use MonteiroTi\Sei\Core\Config\RetryOptions;
use MonteiroTi\Sei\Core\Config\SeiConfig;
use MonteiroTi\Sei\Core\Config\SipConfig;
use MonteiroTi\Sei\Core\Config\TransportOptions;
use MonteiroTi\Sei\Core\Exceptions\ConnectionException;
use MonteiroTi\Sei\Core\Support\CatalogCache;
use MonteiroTi\Sei\Core\Support\Redactor;
use MonteiroTi\Sei\Core\Transport\FakeTransport;
use MonteiroTi\Sei\Core\Transport\RetryingTransport;
use MonteiroTi\Sei\Core\Transport\SoapTransport;
use MonteiroTi\Sei\Core\Transport\Transport;
use MonteiroTi\Sei\Laravel\Integracao\Idempotencia;
use MonteiroTi\Sei\Laravel\Integracao\SeiIntegracao;
use MonteiroTi\Sei\Laravel\Sincronizacao\Sincronizador;
use MonteiroTi\Sei\Laravel\Testing\SeiFake;
use MonteiroTi\Sei\SeiClient;
use MonteiroTi\Sei\Sip\SipClient;
use Psr\Log\LoggerInterface;

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

    /**
     * Operações com idempotência, vínculo no banco e rastreabilidade (módulo de persistência).
     */
    public function integracao(?string $conexao = null): SeiIntegracao
    {
        $conexao ??= $this->getDefaultConnection();
        $config = $this->config('integracao', []);

        return new SeiIntegracao($this->connection($conexao), $conexao, $this->idempotencia(), is_array($config) ? $config : []);
    }

    public function sincronizador(): Sincronizador
    {
        $config = $this->config('sincronizacao', []);

        return new Sincronizador($this, $this->app->make('events'), $this->redactor(), is_array($config) ? $config : []);
    }

    public function idempotencia(): Idempotencia
    {
        $store = $this->app->make('cache')->store($this->config('integracao.lock_store'))->getStore();
        if (! $store instanceof LockProvider) {
            throw new \InvalidArgumentException('O cache store usado em sei.integracao.lock_store precisa suportar locks (redis, database, file, array...).');
        }

        return new Idempotencia($store, $this->redactor(), (int) $this->config('integracao.lock_segundos', 300));
    }

    /**
     * ID do SEI configurado em connections.{conexao}.parametros, ex.: parametro('series.portaria').
     */
    public function parametro(string $chave, ?string $conexao = null): string
    {
        $conexao ??= $this->getDefaultConnection();
        $valor = Arr::get($this->connectionConfig($conexao)['parametros'] ?? [], $chave);

        if (! is_scalar($valor) || (string) $valor === '') {
            throw new \InvalidArgumentException("Parâmetro SEI [{$chave}] não configurado na conexão [{$conexao}].");
        }

        return (string) $valor;
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
     * Camadas: Retry(Observed(Soap)). Cada tentativa gera seu próprio log e evento.
     * Com Sei::fake() não há retry, para os testes continuarem determinísticos.
     *
     * @param  array<string, mixed>  $config
     */
    private function transport(string $connection, array $config): Transport
    {
        if ($this->fake !== null) {
            return $this->observed($this->fake, $connection);
        }

        $transport = $this->observed(new SoapTransport(TransportOptions::fromArray($config)), $connection);

        $retry = RetryOptions::fromConfig($config['retry'] ?? null);
        if ($retry !== null) {
            $logger = $this->logger();
            $transport = new RetryingTransport(
                $transport,
                $retry,
                aoRepetir: $logger === null ? null : function (string $operation, int $tentativa, ConnectionException $e, int $espera) use ($logger, $connection): void {
                    $logger->warning("SEI {$operation}: nova tentativa {$tentativa}", [
                        'connection' => $connection,
                        'espera_ms' => $espera,
                        'message' => $this->redactor()->text($e->getMessage()),
                    ]);
                },
            );
        }

        return $transport;
    }

    private function observed(Transport $transport, string $connection): Transport
    {
        $events = $this->config('events', true) ? $this->app->make('events') : null;
        $logger = $this->logger();

        if ($events === null && $logger === null) {
            return $transport;
        }

        return new ObservedTransport($transport, $connection, $events, $logger, $this->redactor());
    }

    private function logger(): ?LoggerInterface
    {
        if (! $this->config('log.enabled', false)) {
            return null;
        }

        return $this->app->make('log')->channel($this->config('log.channel'));
    }

    public function redactor(): Redactor
    {
        $extras = $this->config('log.chaves_extras', []);

        return new Redactor(
            (string) $this->config('log.dados_pessoais', Redactor::MODO_MASCARAR),
            is_array($extras) ? array_values(array_map('strval', $extras)) : [],
        );
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

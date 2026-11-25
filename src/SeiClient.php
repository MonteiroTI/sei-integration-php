<?php

declare(strict_types=1);

namespace MonteiroTi\Sei;

use MonteiroTi\Sei\Api\Andamentos;
use MonteiroTi\Sei\Api\Blocos;
use MonteiroTi\Sei\Api\Catalogos;
use MonteiroTi\Sei\Api\Contatos;
use MonteiroTi\Sei\Api\Documentos;
use MonteiroTi\Sei\Api\Ouvidoria;
use MonteiroTi\Sei\Api\Processos;
use MonteiroTi\Sei\Api\Publicacoes;
use MonteiroTi\Sei\Core\Config\CircuitOptions;
use MonteiroTi\Sei\Core\Config\RetryOptions;
use MonteiroTi\Sei\Core\Config\SeiConfig;
use MonteiroTi\Sei\Core\Config\TransportOptions;
use MonteiroTi\Sei\Core\Exceptions\AccessDeniedException;
use MonteiroTi\Sei\Core\Exceptions\InvalidArgumentException;
use MonteiroTi\Sei\Core\Support\CatalogCache;
use MonteiroTi\Sei\Core\Transport\CircuitBreakerTransport;
use MonteiroTi\Sei\Core\Transport\CircuitState;
use MonteiroTi\Sei\Core\Transport\InMemoryCircuitState;
use MonteiroTi\Sei\Core\Transport\RetryingTransport;
use MonteiroTi\Sei\Core\Transport\SoapTransport;
use MonteiroTi\Sei\Core\Transport\Transport;
use MonteiroTi\Sei\Operations\SeiOperations;

/**
 * Cliente do WebService do SEI (controlador_ws.php?servico=sei).
 *
 * Injeta SiglaSistema, IdentificacaoServico e a unidade em todas as chamadas.
 * É imutável: naUnidade() devolve uma cópia operando em outra unidade.
 */
final class SeiClient
{
    public function __construct(
        private readonly Transport $transport,
        private readonly SeiConfig $config,
        private readonly ?CatalogCache $cache = null,
    ) {}

    /**
     * Cria o cliente a partir de um array de configuração.
     *
     * Chaves: wsdl, sigla_sistema, identificacao_servico, id_unidade, location, encoding,
     * conteudo_charset, timeout, connection_timeout, verify_ssl, wsdl_cache, upload_chunk_size,
     * proxy_host, proxy_port, soap_options, retry (false desliga), circuito (desligado por padrão).
     *
     * @param  array<string, mixed>  $config
     */
    public static function create(array $config, ?CatalogCache $cache = null): self
    {
        return new self(self::transportFromConfig($config), SeiConfig::fromArray($config), $cache);
    }

    /**
     * Monta o transporte SOAP com as camadas configuradas: Circuito(Retry(Soap)).
     *
     * Fora do Laravel o circuito fica em memória do processo e só é ligado com a opção
     * "circuito" (true ou array). Passe um CircuitState compartilhado para usá-lo no PHP-FPM.
     *
     * @param  array<string, mixed>  $config
     */
    public static function transportFromConfig(array $config, string $chave = 'sei', ?CircuitState $circuitState = null): Transport
    {
        $transport = new SoapTransport(TransportOptions::fromArray($config));

        $retry = RetryOptions::fromConfig($config['retry'] ?? null);
        if ($retry !== null) {
            $transport = new RetryingTransport($transport, $retry);
        }

        $circuito = CircuitOptions::fromConfig($config['circuito'] ?? null, padraoAtivo: false);
        if ($circuito !== null) {
            $transport = new CircuitBreakerTransport($transport, $circuito, $circuitState ?? new InMemoryCircuitState, $chave);
        }

        return $transport;
    }

    /** Cópia do cliente operando na unidade informada. */
    public function naUnidade(string $idUnidade): self
    {
        return new self($this->transport, $this->config->withUnidade($idUnidade), $this->cache);
    }

    public function processos(): Processos
    {
        return new Processos($this);
    }

    public function andamentos(): Andamentos
    {
        return new Andamentos($this);
    }

    public function documentos(): Documentos
    {
        return new Documentos($this);
    }

    public function blocos(): Blocos
    {
        return new Blocos($this);
    }

    public function publicacoes(): Publicacoes
    {
        return new Publicacoes($this);
    }

    public function contatos(): Contatos
    {
        return new Contatos($this);
    }

    public function ouvidoria(): Ouvidoria
    {
        return new Ouvidoria($this);
    }

    /**
     * Operações do catálogo liberadas nesta conexão.
     *
     * @return list<string>
     */
    public function operacoesPermitidas(): array
    {
        return array_values(array_filter(SeiOperations::all(), $this->config->permite(...)));
    }

    /**
     * @param  bool  $cache  Usa o cache de catálogos configurado (se houver)
     */
    public function catalogos(bool $cache = true): Catalogos
    {
        return new Catalogos($this, $cache ? $this->cache : null);
    }

    public function config(): SeiConfig
    {
        return $this->config;
    }

    public function transport(): Transport
    {
        return $this->transport;
    }

    /** Circuit breaker da conexão (null se desligado). */
    public function circuito(): ?CircuitBreakerTransport
    {
        return $this->transport instanceof CircuitBreakerTransport ? $this->transport : null;
    }

    /**
     * Chamada genérica a uma operação, com parâmetros nomeados como no manual.
     *
     * As credenciais são preenchidas automaticamente. IdUnidade usa a unidade do cliente
     * quando não informado. Parâmetros omitidos são enviados como null.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function call(string $operation, array $parameters = []): mixed
    {
        $names = SeiOperations::parameters($operation);

        if (! $this->config->permite($operation)) {
            throw new AccessDeniedException(
                sprintf('A operação %s não está liberada nesta conexão (opções "operacoes"/"somente_leitura").', $operation),
                $operation,
            );
        }

        $unknown = array_diff(array_keys($parameters), $names);
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf(
                'Parâmetro(s) desconhecido(s) para %s: %s.',
                $operation,
                implode(', ', $unknown),
            ));
        }

        $parameters['SiglaSistema'] = $this->config->siglaSistema;
        $parameters['IdentificacaoServico'] = $this->config->identificacaoServico;

        if (SeiOperations::requiresUnidade($operation) && ! isset($parameters['IdUnidade'])) {
            $parameters['IdUnidade'] = $this->config->idUnidade
                ?? throw new InvalidArgumentException(sprintf(
                    'A operação %s exige uma unidade: configure id_unidade ou use naUnidade().',
                    $operation,
                ));
        }

        $ordered = [];
        foreach ($names as $name) {
            $ordered[$name] = $parameters[$name] ?? null;
        }

        return $this->transport->call($operation, $ordered);
    }
}

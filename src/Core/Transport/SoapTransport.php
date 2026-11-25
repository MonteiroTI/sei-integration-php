<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Transport;

use MonteiroTi\Sei\Core\Config\TransportOptions;
use MonteiroTi\Sei\Core\Exceptions\ConnectionException;
use MonteiroTi\Sei\Core\Exceptions\FaultMapper;
use SoapClient;
use SoapFault;

/**
 * Transporte baseado no \SoapClient nativo (ext-soap).
 *
 * A WSDL do SEI é RPC/encoded: os argumentos são posicionais, por isso
 * os valores nomeados recebidos são enviados na ordem em que chegam.
 *
 * O \SoapClient lê a resposta com o default_socket_timeout do PHP (o timeout do
 * stream_context não vale para ele), por isso o timeout da conexão é aplicado com
 * ini_set durante a chamada. Esse valor é global do processo: no Octane com Swoole e
 * corrotinas, chamadas simultâneas de conexões com timeouts diferentes podem usar o
 * timeout uma da outra. Nesse cenário, use o mesmo timeout em todas as conexões.
 */
final class SoapTransport implements Transport
{
    private ?SoapClient $client = null;

    /** @var (\Closure(array<string, mixed>): SoapClient)|null */
    private ?\Closure $factory;

    /**
     * @param  (\Closure(array<string, mixed>): SoapClient)|null  $factory  Fábrica alternativa (testes)
     */
    public function __construct(
        private readonly TransportOptions $options,
        ?\Closure $factory = null,
    ) {
        $this->factory = $factory;
    }

    public function call(string $operation, array $arguments): mixed
    {
        $client = $this->client();
        $timeout = (string) $this->options->timeout;
        $previousTimeout = ini_get('default_socket_timeout');
        $changed = $previousTimeout !== $timeout;
        if ($changed) {
            ini_set('default_socket_timeout', $timeout);
        }

        try {
            return $client->__soapCall($operation, array_values($arguments));
        } catch (SoapFault $fault) {
            throw FaultMapper::map($fault, $operation);
        } finally {
            if ($changed && $previousTimeout !== false) {
                ini_set('default_socket_timeout', $previousTimeout);
            }
        }
    }

    /**
     * Assinaturas das operações declaradas na WSDL (\SoapClient::__getFunctions()).
     *
     * @return list<string>
     */
    public function functions(): array
    {
        return array_values($this->client()->__getFunctions() ?? []);
    }

    public function lastRequest(): ?string
    {
        return $this->client?->__getLastRequest();
    }

    public function lastResponse(): ?string
    {
        return $this->client?->__getLastResponse();
    }

    private function client(): SoapClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $options = $this->soapOptions();

        // O SoapClient emite warnings do libxml junto com o SoapFault quando a WSDL falha.
        // Em apps Laravel esses warnings virariam ErrorException; aqui viram ConnectionException.
        set_error_handler(fn (): bool => true, E_WARNING | E_NOTICE | E_DEPRECATED);

        try {
            $this->client = $this->factory !== null
                ? ($this->factory)($options)
                : new SoapClient($this->options->wsdl, $options);
        } catch (SoapFault $fault) {
            throw new ConnectionException(
                sprintf('Não foi possível carregar a WSDL %s: %s', $this->options->wsdl, $fault->getMessage()),
                previous: $fault,
                possivelmenteEnviada: false,
            );
        } finally {
            restore_error_handler();
        }

        return $this->client;
    }

    /**
     * Baixa a WSDL com as mesmas opções de SSL, proxy e timeout da conexão.
     */
    public function downloadWsdl(): string
    {
        $o = $this->options;
        $http = ['timeout' => $o->timeout, 'user_agent' => 'monteiroti-sei-integration', 'ignore_errors' => false];
        if ($o->proxyHost !== null) {
            $http['proxy'] = 'tcp://'.$o->proxyHost.':'.($o->proxyPort ?? 80);
            $http['request_fulluri'] = true;
        }
        $ssl = $o->verifySsl
            ? ['verify_peer' => true, 'verify_peer_name' => true]
            : ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true];

        set_error_handler(fn (): bool => true, E_WARNING);
        try {
            $conteudo = file_get_contents($o->wsdl, false, stream_context_create(['http' => $http, 'ssl' => $ssl]));
        } finally {
            restore_error_handler();
        }

        if ($conteudo === false || ! str_contains($conteudo, 'definitions')) {
            throw new ConnectionException(sprintf('Não foi possível baixar a WSDL %s.', $o->wsdl), possivelmenteEnviada: false);
        }

        return $conteudo;
    }

    /**
     * @return array<string, mixed>
     */
    public function soapOptions(): array
    {
        $o = $this->options;

        $ssl = $o->verifySsl
            ? ['verify_peer' => true, 'verify_peer_name' => true]
            : ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true];

        $options = [
            'soap_version' => SOAP_1_1,
            'exceptions' => true,
            'trace' => true,
            'cache_wsdl' => $o->wsdlCache,
            'connection_timeout' => $o->connectionTimeout,
            'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
            'stream_context' => stream_context_create([
                'ssl' => $ssl,
                'http' => ['timeout' => $o->timeout, 'user_agent' => 'monteiroti-sei-integration'],
            ]),
        ];

        if (strtoupper($o->encoding) !== 'UTF-8') {
            $options['encoding'] = $o->encoding;
        }
        if ($o->location !== null) {
            $options['location'] = $o->location;
        }
        if ($o->proxyHost !== null) {
            $options['proxy_host'] = $o->proxyHost;
            $options['proxy_port'] = $o->proxyPort ?? 80;
        }

        return array_replace($options, $o->soapOptions);
    }
}

<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Config;

/**
 * Opções de transporte SOAP comuns ao SEI e ao SIP.
 */
final readonly class TransportOptions
{
    /**
     * @param  string  $wsdl  URL da WSDL, ex.: https://sei.orgao.gov.br/sei/controlador_ws.php?servico=sei
     * @param  string|null  $location  Endpoint a usar no lugar do informado na WSDL (útil atrás de proxy reverso)
     * @param  string  $encoding  Encoding interno das strings da aplicação. Em apps Laravel é UTF-8.
     * @param  int  $timeout  Tempo máximo (s) para ler a resposta
     * @param  int  $connectionTimeout  Tempo máximo (s) para abrir a conexão
     * @param  bool  $verifySsl  Valida o certificado do servidor
     * @param  int  $wsdlCache  Uma das constantes WSDL_CACHE_*
     * @param  array<string, mixed>  $soapOptions  Opções extras repassadas ao \SoapClient
     */
    public function __construct(
        public string $wsdl,
        public ?string $location = null,
        public string $encoding = 'UTF-8',
        public int $timeout = 60,
        public int $connectionTimeout = 10,
        public bool $verifySsl = true,
        public int $wsdlCache = WSDL_CACHE_BOTH,
        public ?string $proxyHost = null,
        public ?int $proxyPort = null,
        public array $soapOptions = [],
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        if (empty($config['wsdl']) || ! is_string($config['wsdl'])) {
            throw new \InvalidArgumentException('A opção "wsdl" é obrigatória.');
        }

        return new self(
            wsdl: $config['wsdl'],
            location: isset($config['location']) ? (string) $config['location'] : null,
            encoding: (string) ($config['encoding'] ?? 'UTF-8'),
            timeout: (int) ($config['timeout'] ?? 60),
            connectionTimeout: (int) ($config['connection_timeout'] ?? 10),
            verifySsl: (bool) ($config['verify_ssl'] ?? true),
            wsdlCache: (int) ($config['wsdl_cache'] ?? WSDL_CACHE_BOTH),
            proxyHost: isset($config['proxy_host']) ? (string) $config['proxy_host'] : null,
            proxyPort: isset($config['proxy_port']) ? (int) $config['proxy_port'] : null,
            soapOptions: (array) ($config['soap_options'] ?? []),
        );
    }
}

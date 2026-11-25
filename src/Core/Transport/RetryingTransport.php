<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Transport;

use Closure;
use MonteiroTi\Sei\Core\Config\RetryOptions;
use MonteiroTi\Sei\Core\Config\SeiConfig;
use MonteiroTi\Sei\Core\Exceptions\ConnectionException;

/**
 * Decorator que repete operações de leitura (consultar*, listar*) após falhas de conexão.
 *
 * Escritas nunca são repetidas aqui: um timeout depois do envio poderia duplicar o efeito
 * no SEI. Erros de negócio (FaultException, AccessDeniedException...) sobem na hora.
 */
final class RetryingTransport implements Transport
{
    /** @var Closure(int): void */
    private Closure $sleep;

    /**
     * @param  (Closure(int $ms): void)|null  $sleep  Espera entre tentativas (padrão: usleep)
     * @param  (Closure(string $operation, int $tentativa, ConnectionException $e, int $esperaMs): void)|null  $aoRepetir  Chamado antes de cada nova tentativa
     */
    public function __construct(
        private readonly Transport $inner,
        private readonly RetryOptions $options,
        ?Closure $sleep = null,
        private readonly ?Closure $aoRepetir = null,
    ) {
        $this->sleep = $sleep ?? static function (int $ms): void {
            usleep($ms * 1000);
        };
    }

    public function call(string $operation, array $arguments): mixed
    {
        if (! SeiConfig::leitura($operation)) {
            return $this->inner->call($operation, $arguments);
        }

        $tentativa = 0;
        while (true) {
            try {
                return $this->inner->call($operation, $arguments);
            } catch (ConnectionException $e) {
                if (++$tentativa > $this->options->tentativas) {
                    throw $e;
                }

                $espera = $this->espera($tentativa);
                if ($this->aoRepetir !== null) {
                    ($this->aoRepetir)($operation, $tentativa, $e, $espera);
                }
                ($this->sleep)($espera);
            }
        }
    }

    public function inner(): Transport
    {
        return $this->inner;
    }

    public function options(): RetryOptions
    {
        return $this->options;
    }

    private function espera(int $tentativa): int
    {
        $ms = $this->options->espera($tentativa);

        return $this->options->jitter && $ms > 1 ? random_int(intdiv($ms, 2), $ms) : $ms;
    }
}

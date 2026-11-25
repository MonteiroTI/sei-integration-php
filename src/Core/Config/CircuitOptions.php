<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Config;

/**
 * Circuit breaker por conexão: depois de várias falhas de conexão seguidas, as chamadas
 * falham na hora (CircuitoAbertoException) por um tempo, em vez de esperar o timeout.
 */
final readonly class CircuitOptions
{
    /**
     * @param  int  $falhas  Falhas de conexão dentro da janela que abrem o circuito
     * @param  int  $janelaSegundos  Janela em que as falhas são contadas
     * @param  int  $abertoSegundos  Tempo em que o circuito fica aberto antes de testar de novo
     */
    public function __construct(
        public int $falhas = 5,
        public int $janelaSegundos = 60,
        public int $abertoSegundos = 30,
    ) {
        if ($falhas < 1 || $janelaSegundos < 1 || $abertoSegundos < 1) {
            throw new \InvalidArgumentException('Opções do circuito inválidas: use valores maiores que zero.');
        }
    }

    /**
     * Lê a opção "circuito" da conexão. False desliga; true ou um array liga.
     * Ausente (null) usa $padraoAtivo.
     */
    public static function fromConfig(mixed $config, bool $padraoAtivo): ?self
    {
        if ($config === false || ($config === null && ! $padraoAtivo)) {
            return null;
        }
        if (! is_array($config)) {
            return new self;
        }

        return new self(
            falhas: (int) ($config['falhas'] ?? 5),
            janelaSegundos: (int) ($config['janela_segundos'] ?? 60),
            abertoSegundos: (int) ($config['aberto_segundos'] ?? 30),
        );
    }
}

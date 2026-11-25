<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Config;

/**
 * Política de novas tentativas para operações de leitura (consultar*, listar*).
 *
 * Só falhas de conexão (ConnectionException) são repetidas. Operações de escrita
 * nunca são repetidas pelo transporte: use o módulo de idempotência.
 */
final readonly class RetryOptions
{
    /**
     * @param  int  $tentativas  Novas tentativas depois da primeira chamada (0 desliga)
     * @param  int  $esperaMs  Espera antes da primeira nova tentativa
     * @param  float  $multiplicador  Fator de crescimento da espera a cada tentativa
     * @param  int  $esperaMaxMs  Espera máxima entre tentativas
     * @param  bool  $jitter  Sorteia a espera entre a metade e o valor calculado (evita rajadas sincronizadas)
     */
    public function __construct(
        public int $tentativas = 2,
        public int $esperaMs = 200,
        public float $multiplicador = 2.0,
        public int $esperaMaxMs = 2000,
        public bool $jitter = true,
    ) {
        if ($tentativas < 0 || $esperaMs < 0 || $esperaMaxMs < 0 || $multiplicador < 1) {
            throw new \InvalidArgumentException('Opções de retry inválidas: use valores não negativos e multiplicador >= 1.');
        }
    }

    /**
     * Lê a opção "retry" da conexão. False ou tentativas = 0 desligam; ausente (null) usa o padrão.
     */
    public static function fromConfig(mixed $config): ?self
    {
        if ($config === false) {
            return null;
        }
        if (! is_array($config)) {
            return new self;
        }

        $options = new self(
            tentativas: (int) ($config['tentativas'] ?? 2),
            esperaMs: (int) ($config['espera_ms'] ?? 200),
            multiplicador: (float) ($config['multiplicador'] ?? 2.0),
            esperaMaxMs: (int) ($config['espera_max_ms'] ?? 2000),
            jitter: (bool) ($config['jitter'] ?? true),
        );

        return $options->tentativas === 0 ? null : $options;
    }

    /** Espera (ms) antes da tentativa extra de número $n (1 = primeira nova tentativa), sem jitter. */
    public function espera(int $n): int
    {
        $ms = $this->esperaMs * ($this->multiplicador ** max(0, $n - 1));

        return (int) min($this->esperaMaxMs, $ms);
    }
}

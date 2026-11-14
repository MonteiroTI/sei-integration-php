<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Api;

use MonteiroTi\Sei\Core\Exceptions\InvalidArgumentException;
use MonteiroTi\Sei\SeiClient;

abstract class Api
{
    public function __construct(protected readonly SeiClient $client) {}

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function call(string $operation, array $parameters = []): mixed
    {
        return $this->client->call($operation, $parameters);
    }

    /**
     * Garante que exatamente um (ou ao menos um) dos identificadores foi informado.
     *
     * @param  array<string, mixed>  $values
     */
    protected function requireOne(array $values, bool $exactlyOne = true): void
    {
        $filled = count(array_filter($values, fn ($v) => $v !== null && $v !== ''));
        $names = implode(', ', array_keys($values));

        if ($filled === 0) {
            throw new InvalidArgumentException("Informe um dos parâmetros: {$names}.");
        }
        if ($exactlyOne && $filled > 1) {
            throw new InvalidArgumentException("Informe apenas um dos parâmetros: {$names}.");
        }
    }
}

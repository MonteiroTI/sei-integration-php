<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel;

use MonteiroTi\Sei\Sip\SipClient;

/**
 * Acesso às conexões SIP (facade Sip).
 *
 * @mixin SipClient
 */
class SipManager
{
    public function __construct(private readonly SeiManager $manager) {}

    public function connection(?string $name = null): SipClient
    {
        return $this->manager->sip($name);
    }

    /**
     * @param  array<int, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->connection()->{$method}(...$parameters);
    }
}

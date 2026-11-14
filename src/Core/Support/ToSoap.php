<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Support;

/**
 * Estrutura que pode ser enviada ao WebService.
 */
interface ToSoap
{
    /**
     * Representação no formato esperado pela WSDL (chaves com os nomes do SEI).
     *
     * @return array<string, mixed>
     */
    public function toSoap(): array;
}

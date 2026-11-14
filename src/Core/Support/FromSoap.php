<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Support;

/**
 * Estrutura que pode ser lida de uma resposta do WebService.
 */
interface FromSoap
{
    public static function fromSoap(SoapData $data): static;
}

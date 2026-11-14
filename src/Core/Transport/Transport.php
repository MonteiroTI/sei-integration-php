<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Transport;

use MonteiroTi\Sei\Core\Exceptions\SeiException;

interface Transport
{
    /**
     * Executa uma operação do WebService.
     *
     * @param  string  $operation  Nome da operação na WSDL, ex.: gerarProcedimento
     * @param  array<string, mixed>  $arguments  Argumentos nomeados, já na ordem posicional da WSDL
     *
     * @throws SeiException
     */
    public function call(string $operation, array $arguments): mixed;
}

<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Exceptions;

use SoapFault;

/**
 * Converte SoapFault em exceções tipadas da biblioteca.
 */
final class FaultMapper
{
    /** Operações cujos erros chegam acumulados (um por item do conjunto). */
    private const ACCUMULATING_OPERATIONS = ['atualizarContatos', 'replicarUsuario', 'replicarPermissao'];

    private const ACCESS_DENIED_PATTERNS = [
        '/acesso\s+negado/iu',
        '/n[ãa]o\s+(possui|tem)\s+(acesso|permiss[ãa]o)/iu',
        '/n[ãa]o\s+(est[áa]\s+)?(liberad|autorizad)/iu',
        '/chave\s+de\s+acesso\s+inv[áa]lida/iu',
        '/(sistema|servi[çc]o)\s+.*n[ãa]o\s+encontrad/iu',
    ];

    private const CONNECTION_PATTERNS = [
        '/could not connect/i',
        '/could not resolve host/i',
        '/error fetching http headers/i',
        '/failed to load external entity/i',
        '/parsing wsdl/i',
        '/connection (refused|reset|timed out)/i',
        '/ssl (operation failed|routines)/i',
    ];

    /** Falhas em que a requisição certamente não chegou ao servidor. */
    private const NOT_SENT_PATTERNS = [
        '/could not connect/i',
        '/could not resolve host/i',
        '/failed to load external entity/i',
        '/parsing wsdl/i',
        '/connection refused/i',
        '/ssl (operation failed|routines)/i',
    ];

    public static function map(SoapFault $fault, string $operation): SeiException
    {
        $message = trim((string) $fault->getMessage());
        $code = isset($fault->faultcode) ? (string) $fault->faultcode : null;

        if (in_array($code, ['HTTP', 'WSDL'], true) || self::matches($message, self::CONNECTION_PATTERNS)) {
            $naoEnviada = $code === 'WSDL' || self::matches($message, self::NOT_SENT_PATTERNS);

            return new ConnectionException($message, $operation, 0, $fault, possivelmenteEnviada: ! $naoEnviada);
        }

        if (self::matches($message, self::ACCESS_DENIED_PATTERNS)) {
            return new AccessDeniedException($message, $operation, $code, $fault);
        }

        if (in_array($operation, self::ACCUMULATING_OPERATIONS, true)) {
            return new ValidationException($message, $operation, $code, $fault);
        }

        return new FaultException($message, $operation, $code, $fault);
    }

    /**
     * @param  list<string>  $patterns
     */
    private static function matches(string $message, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message) === 1) {
                return true;
            }
        }

        return false;
    }
}

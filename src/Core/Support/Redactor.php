<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Support;

/**
 * Prepara argumentos para log: mascara credenciais e resume conteúdos longos (Base64).
 */
final class Redactor
{
    public const SECRET_KEYS = ['IdentificacaoServico', 'ChaveAcesso'];

    public const MAX_LENGTH = 200;

    /**
     * @param  array<array-key, mixed>  $arguments
     * @return array<array-key, mixed>
     */
    public static function redact(array $arguments): array
    {
        $out = [];
        foreach ($arguments as $key => $value) {
            if (is_string($key) && in_array($key, self::SECRET_KEYS, true) && $value !== null) {
                $out[$key] = '***';

                continue;
            }
            $out[$key] = self::value($value);
        }

        return $out;
    }

    private static function value(mixed $value): mixed
    {
        if (is_array($value)) {
            return self::redact($value);
        }
        if (is_object($value)) {
            return self::redact(get_object_vars($value));
        }
        if (is_string($value) && strlen($value) > self::MAX_LENGTH) {
            return sprintf('[%d bytes] %s…', strlen($value), substr($value, 0, 40));
        }

        return $value;
    }
}

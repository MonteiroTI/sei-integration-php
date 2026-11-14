<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Support;

final class Charset
{
    /** Converte texto UTF-8 para o charset de destino (sem efeito quando o destino é UTF-8). */
    public static function fromUtf8(string $text, string $charset): string
    {
        if (strtoupper($charset) === 'UTF-8' || $text === '') {
            return $text;
        }

        return mb_convert_encoding($text, $charset, 'UTF-8');
    }
}

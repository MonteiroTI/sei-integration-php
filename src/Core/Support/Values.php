<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Support;

use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Conversões entre tipos PHP e as convenções do WebService do SEI.
 */
final class Values
{
    public const DATE_FORMAT = 'd/m/Y';

    public const DATETIME_FORMAT = 'd/m/Y H:i:s';

    private static ?DateTimeZone $timezone = null;

    /** Fuso usado na leitura de datas devolvidas pelo SEI (padrão: America/Sao_Paulo). */
    public static function setTimezone(?DateTimeZone $timezone): void
    {
        self::$timezone = $timezone;
    }

    public static function timezone(): DateTimeZone
    {
        return self::$timezone ??= new DateTimeZone('America/Sao_Paulo');
    }

    /** bool → 'S'/'N' (null permanece null para o SEI aplicar o padrão). */
    public static function sn(?bool $value): ?string
    {
        return $value === null ? null : ($value ? 'S' : 'N');
    }

    /** 'S'/'N' (ou bool) → bool. */
    public static function bool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtoupper((string) $value), ['S', 'SIM', 'TRUE', '1'], true);
    }

    public static function date(?DateTimeInterface $value): ?string
    {
        return $value?->format(self::DATE_FORMAT);
    }

    public static function dateTime(?DateTimeInterface $value): ?string
    {
        return $value?->format(self::DATETIME_FORMAT);
    }

    /** Lê 'dd/mm/aaaa', 'dd/mm/aaaa hh:mm[:ss]' ou ISO-8601. */
    public static function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        $value = trim((string) $value);
        $formats = ['!d/m/Y H:i:s', '!d/m/Y H:i', '!d/m/Y', '!Y-m-d H:i:s', '!Y-m-d'];

        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value, self::timezone());
            if ($date !== false && DateTimeImmutable::getLastErrors() === false) {
                return $date;
            }
        }

        try {
            return new DateTimeImmutable($value, self::timezone());
        } catch (\Exception) {
            return null;
        }
    }

    /** Identificadores do SEI passam de 32 bits: sempre string. */
    public static function id(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    public static function enum(?BackedEnum $value): int|string|null
    {
        return $value?->value;
    }

    /**
     * Converte recursivamente objetos que implementam ToSoap.
     *
     * @param  iterable<mixed>|null  $items
     * @return list<mixed>
     */
    public static function list(?iterable $items): array
    {
        $out = [];
        foreach ($items ?? [] as $item) {
            $out[] = $item instanceof ToSoap ? $item->toSoap() : $item;
        }

        return $out;
    }

    /**
     * @param  iterable<int|string|\Stringable>|null  $ids
     * @return list<string>
     */
    public static function ids(?iterable $ids): array
    {
        $out = [];
        foreach ($ids ?? [] as $id) {
            $out[] = (string) $id;
        }

        return $out;
    }
}

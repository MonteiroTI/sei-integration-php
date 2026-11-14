<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Support;

use BackedEnum;
use DateTimeImmutable;

/**
 * Leitor tolerante para os objetos devolvidos pelo \SoapClient.
 *
 * Normaliza stdClass/array, listas com um só item, strings vazias e
 * a conversão de 'S'/'N', datas e enums.
 */
final class SoapData
{
    /** @var array<string, mixed> */
    private array $values;

    /**
     * @param  array<string, mixed>  $values
     */
    private function __construct(array $values)
    {
        $this->values = $values;
    }

    public static function from(mixed $data): self
    {
        if ($data instanceof self) {
            return $data;
        }
        if (is_object($data)) {
            /** @var array<string, mixed> $vars */
            $vars = get_object_vars($data);

            return new self($vars);
        }
        if (is_array($data)) {
            /** @var array<string, mixed> $data */
            return new self($data);
        }

        return new self([]);
    }

    /**
     * Normaliza qualquer retorno de "conjunto" do SOAP em lista PHP.
     *
     * @return list<mixed>
     */
    public static function listOf(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            return array_is_list($value) ? $value : [$value];
        }
        if (is_object($value)) {
            $vars = get_object_vars($value);
            // Arrays literais podem chegar como { item: [...] }
            if (count($vars) === 1 && array_key_exists('item', $vars)) {
                return self::listOf($vars['item']);
            }

            return [$value];
        }

        return [$value];
    }

    /**
     * Hidrata uma lista de DTOs a partir de um retorno de conjunto.
     *
     * @template T of FromSoap
     *
     * @param  class-string<T>  $class
     * @return list<T>
     */
    public static function hydrateList(mixed $value, string $class): array
    {
        return array_map(fn ($item) => $class::fromSoap(self::from($item)), self::listOf($value));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values) && $this->values[$key] !== null;
    }

    public function raw(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function string(string $key): ?string
    {
        $value = $this->values[$key] ?? null;
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }
        $value = (string) $value;

        return $value === '' ? null : $value;
    }

    public function requiredString(string $key): string
    {
        return $this->string($key) ?? '';
    }

    public function id(string $key): ?string
    {
        return Values::id($this->string($key));
    }

    public function int(string $key): ?int
    {
        $value = $this->string($key);

        return $value === null || ! is_numeric($value) ? null : (int) $value;
    }

    public function float(string $key): ?float
    {
        $value = $this->string($key);
        if ($value === null) {
            return null;
        }
        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    public function bool(string $key): ?bool
    {
        return Values::bool($this->values[$key] ?? null);
    }

    public function date(string $key): ?DateTimeImmutable
    {
        return Values::parseDate($this->string($key));
    }

    /**
     * @template E of BackedEnum
     *
     * @param  class-string<E>  $enum
     * @return E|null
     */
    public function enum(string $key, string $enum): ?BackedEnum
    {
        $value = $this->string($key);
        if ($value === null) {
            return null;
        }

        $backing = (new \ReflectionEnum($enum))->getBackingType()?->getName();

        return $enum::tryFrom($backing === 'int' ? (int) $value : $value);
    }

    /**
     * @template T of FromSoap
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    public function object(string $key, string $class): ?FromSoap
    {
        $value = $this->values[$key] ?? null;
        if ($value === null || $value === '' || (is_array($value) && $value === [])) {
            return null;
        }

        return $class::fromSoap(self::from($value));
    }

    /**
     * @template T of FromSoap
     *
     * @param  class-string<T>  $class
     * @return list<T>
     */
    public function list(string $key, string $class): array
    {
        return self::hydrateList($this->values[$key] ?? null, $class);
    }

    /**
     * @return list<string>
     */
    public function strings(string $key): array
    {
        return array_values(array_map('strval', array_filter(
            self::listOf($this->values[$key] ?? null),
            fn ($v) => is_scalar($v),
        )));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->values;
    }
}

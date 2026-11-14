<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Operations;

/**
 * Compara o catálogo de operações da biblioteca com as assinaturas de uma WSDL
 * (\SoapClient::__getFunctions()).
 */
final class WsdlContract
{
    /**
     * Converte assinaturas como "Tipo op(string $A, ArrayOfX $B)" em [op => [A, B]].
     *
     * @param  list<string>  $functions
     * @return array<string, list<string>>
     */
    public static function parse(array $functions): array
    {
        $out = [];
        foreach ($functions as $signature) {
            if (preg_match('/(\w+)\s*\((.*)\)\s*$/s', $signature, $m) !== 1) {
                continue;
            }
            preg_match_all('/\$(\w+)/', $m[2], $params);
            $out[$m[1]] = $params[1];
        }

        return $out;
    }

    /**
     * @param  list<string>  $functions  Assinaturas da WSDL
     * @param  list<string>  $operations  Operações conhecidas pela biblioteca
     * @param  callable(string): list<string>  $parametersFor
     * @return array{missing: list<string>, extra: list<string>, mismatched: array<string, array{expected: list<string>, actual: list<string>}>}
     */
    public static function compare(array $functions, array $operations, callable $parametersFor): array
    {
        $wsdl = self::parse($functions);

        $missing = array_values(array_diff($operations, array_keys($wsdl)));
        $extra = array_values(array_diff(array_keys($wsdl), $operations));

        $mismatched = [];
        foreach (array_intersect($operations, array_keys($wsdl)) as $operation) {
            $expected = $parametersFor($operation);
            $actual = $wsdl[$operation];
            if (array_map('strtolower', $expected) !== array_map('strtolower', $actual)) {
                $mismatched[$operation] = ['expected' => $expected, 'actual' => $actual];
            }
        }

        return ['missing' => $missing, 'extra' => $extra, 'mismatched' => $mismatched];
    }
}

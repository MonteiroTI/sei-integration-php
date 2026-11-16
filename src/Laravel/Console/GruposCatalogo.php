<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Console;

use Illuminate\Support\Str;
use MonteiroTi\Sei\Api\Catalogos;

/**
 * Grupos de parâmetros (connections.*.parametros) que podem ser conferidos contra os catálogos do SEI.
 *
 * @internal
 */
final class GruposCatalogo
{
    public const GRUPOS = [
        'unidades' => 'Unidades',
        'tipos_processo' => 'Tipos de processo',
        'series' => 'Tipos de documento',
        'hipoteses_legais' => 'Hipóteses legais',
        'tipos_conferencia' => 'Tipos de conferência',
        'tipos_prioridade' => 'Tipos de prioridade',
        'marcadores' => 'Marcadores da unidade',
    ];

    /**
     * Itens do catálogo como [id => rótulo].
     *
     * @return array<string, string>|null Null se o grupo não tiver catálogo correspondente
     */
    public static function itens(Catalogos $c, string $grupo): ?array
    {
        $pares = match ($grupo) {
            'unidades' => array_map(fn ($u) => [$u->idUnidade, $u->sigla ?? $u->descricao], $c->unidades()),
            'tipos_processo' => array_map(fn ($t) => [$t->idTipoProcedimento, $t->nome], $c->tiposProcedimento()),
            'series' => array_map(fn ($s) => [$s->idSerie, $s->nome], $c->series()),
            'hipoteses_legais' => array_map(fn ($h) => [$h->idHipoteseLegal, $h->nome], $c->hipotesesLegais()),
            'tipos_conferencia' => array_map(fn ($t) => [$t->idTipoConferencia, $t->descricao], $c->tiposConferencia()),
            'tipos_prioridade' => array_map(fn ($t) => [$t->idTipoPrioridade, $t->nome], $c->tiposPrioridade()),
            'marcadores' => array_map(fn ($m) => [$m->idMarcador, $m->nome], $c->marcadoresUnidade()),
            default => null,
        };

        if ($pares === null) {
            return null;
        }

        $itens = [];
        foreach ($pares as [$id, $rotulo]) {
            if ($id !== null) {
                $itens[$id] = (string) ($rotulo ?? $id);
            }
        }

        return $itens;
    }

    /** Grupo de parâmetros correspondente a um tipo do sei:catalogo (tipos-processo → tipos_processo). */
    public static function deTipo(string $tipo): ?string
    {
        $grupo = str_replace('-', '_', $tipo);

        return isset(self::GRUPOS[$grupo]) ? $grupo : null;
    }

    /**
     * Trecho de config PHP com chaves derivadas dos rótulos.
     *
     * @param  array<string, string>  $itens
     */
    public static function php(string $grupo, array $itens): string
    {
        $linhas = ["'{$grupo}' => ["];
        $usadas = [];
        foreach ($itens as $id => $rotulo) {
            $chave = Str::slug($rotulo, '_') ?: 'id_'.$id;
            $base = $chave;
            for ($i = 2; isset($usadas[$chave]); $i++) {
                $chave = $base.'_'.$i;
            }
            $usadas[$chave] = true;
            $linhas[] = sprintf("    '%s' => '%s', // %s", $chave, $id, str_replace(["\n", '*/'], [' ', ''], $rotulo));
        }
        $linhas[] = '],';

        return implode(PHP_EOL, $linhas);
    }
}

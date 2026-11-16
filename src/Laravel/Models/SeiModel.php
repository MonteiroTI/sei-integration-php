<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base dos models do módulo: tabela e conexão vêm de sei.persistencia.
 */
abstract class SeiModel extends Model
{
    /** Chave da tabela em sei.persistencia.tabelas. */
    protected const TABELA = '';

    protected $guarded = [];

    public function getTable(): string
    {
        $tabela = config('sei.persistencia.tabelas.'.static::TABELA);

        return is_string($tabela) && $tabela !== '' ? $tabela : 'sei_'.static::TABELA;
    }

    public function getConnectionName(): ?string
    {
        $conexao = config('sei.persistencia.conexao_banco');

        return is_string($conexao) && $conexao !== '' ? $conexao : parent::getConnectionName();
    }

    /**
     * Classe de model configurada em sei.persistencia.models.
     *
     * @template T of SeiModel
     *
     * @param  class-string<T>  $default
     * @return class-string<T>
     */
    public static function classe(string $chave, string $default): string
    {
        $classe = config("sei.persistencia.models.{$chave}");

        /** @var class-string<T> */
        return is_string($classe) && is_a($classe, $default, true) ? $classe : $default;
    }
}

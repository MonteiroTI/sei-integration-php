<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use MonteiroTi\Sei\Laravel\Models\SeiModel;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;

/**
 * Para models do sistema que geram ou acompanham processos no SEI.
 *
 * @mixin Model
 */
trait HasSeiProcesso
{
    /** @return MorphMany<SeiProcesso, $this> */
    public function seiProcessos(): MorphMany
    {
        return $this->morphMany(SeiModel::classe('processo', SeiProcesso::class), 'vinculavel');
    }

    /**
     * Processo mais recente vinculado.
     *
     * @return MorphOne<SeiProcesso, $this>
     */
    public function seiProcesso(): MorphOne
    {
        return $this->morphOne(SeiModel::classe('processo', SeiProcesso::class), 'vinculavel')->latestOfMany();
    }

    public function temProcessoSei(?string $conexao = null): bool
    {
        return $this->seiProcessos()->when($conexao !== null, fn ($q) => $q->where('conexao', $conexao))->exists();
    }
}

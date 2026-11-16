<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Vínculo entre um registro do sistema e um processo no SEI.
 *
 * @property int $id
 * @property string $conexao
 * @property string|null $vinculavel_type
 * @property int|string|null $vinculavel_id
 * @property string $id_procedimento
 * @property string $protocolo_formatado
 * @property string|null $link_acesso
 * @property string|null $id_tipo_procedimento
 * @property string|null $id_unidade_geradora
 * @property int|null $nivel_acesso
 * @property bool $sigiloso
 * @property list<array{id: string|null, sigla: string|null}>|null $unidades_abertas
 * @property bool $aberto
 * @property string|null $ultimo_andamento_id
 * @property Carbon|null $ultimo_andamento_em
 * @property Carbon|null $concluido_em
 * @property bool $sincronizar
 * @property Carbon|null $proxima_sincronizacao_em
 * @property Carbon|null $sincronizado_em
 * @property string|null $erro_sincronizacao
 */
class SeiProcesso extends SeiModel
{
    protected const TABELA = 'processos';

    protected function casts(): array
    {
        return [
            'nivel_acesso' => 'integer',
            'sigiloso' => 'boolean',
            'unidades_abertas' => 'array',
            'aberto' => 'boolean',
            'ultimo_andamento_em' => 'datetime',
            'concluido_em' => 'datetime',
            'sincronizar' => 'boolean',
            'proxima_sincronizacao_em' => 'datetime',
            'sincronizado_em' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function vinculavel(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<SeiDocumento, $this> */
    public function documentos(): HasMany
    {
        return $this->hasMany(SeiModel::classe('documento', SeiDocumento::class), 'sei_processo_id');
    }

    /**
     * IDs das unidades onde o processo está aberto.
     *
     * @return list<string>
     */
    public function idsUnidadesAbertas(): array
    {
        return array_values(array_filter(array_map(fn (array $u) => $u['id'] ?? null, $this->unidades_abertas ?? [])));
    }
}

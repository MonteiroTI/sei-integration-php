<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Documento do SEI criado ou acompanhado pelo sistema.
 *
 * @property int $id
 * @property int $sei_processo_id
 * @property string $conexao
 * @property string $id_documento
 * @property string $documento_formatado
 * @property string|null $link_acesso
 * @property string|null $id_serie
 * @property string|null $tipo
 * @property list<array{nome: string|null, sigla: string|null, cargo: string|null, data_hora: string|null}>|null $assinaturas
 * @property Carbon|null $assinado_em
 * @property string|null $id_publicacao
 * @property string|null $publicacao_estado
 * @property Carbon|null $publicado_em
 * @property bool $sincronizar
 * @property Carbon|null $sincronizado_em
 * @property-read SeiProcesso|null $processo
 */
class SeiDocumento extends SeiModel
{
    protected const TABELA = 'documentos';

    protected function casts(): array
    {
        return [
            'assinaturas' => 'array',
            'assinado_em' => 'datetime',
            'publicado_em' => 'datetime',
            'sincronizar' => 'boolean',
            'sincronizado_em' => 'datetime',
        ];
    }

    /** @return BelongsTo<SeiProcesso, $this> */
    public function processo(): BelongsTo
    {
        return $this->belongsTo(SeiModel::classe('processo', SeiProcesso::class), 'sei_processo_id');
    }

    public function assinado(): bool
    {
        return ! empty($this->assinaturas);
    }
}

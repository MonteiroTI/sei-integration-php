<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MonteiroTi\Sei\Dto\Documento;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;
use MonteiroTi\Sei\Laravel\SeiManager;

/**
 * Inclui um documento em fila, no máximo uma vez por chave. Sem retry automático.
 */
class IncluirDocumentoSeiJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public function __construct(
        public SeiProcesso|Model $alvo,
        public Documento $documento,
        public string $chave,
        public ?string $conexao = null,
    ) {
        $this->timeout = (int) config('sei.connections.'.($conexao ?? config('sei.default', 'default')).'.timeout', 60) + 60;
    }

    public function uniqueId(): string
    {
        return ($this->conexao ?? 'default').':'.$this->chave;
    }

    public function handle(SeiManager $sei): void
    {
        $sei->integracao($this->conexao)->incluirDocumento($this->alvo, $this->documento, $this->chave);
    }
}

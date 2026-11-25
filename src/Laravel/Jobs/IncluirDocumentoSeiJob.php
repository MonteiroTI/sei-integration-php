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
use MonteiroTi\Sei\Laravel\Jobs\Concerns\TrataFalhasSei;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;
use MonteiroTi\Sei\Laravel\SeiManager;

/**
 * Inclui um documento em fila, no máximo uma vez por chave. Falhas seguras voltam à fila
 * com backoff; operações incertas e erros de negócio falham (veja TrataFalhasSei).
 */
class IncluirDocumentoSeiJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TrataFalhasSei;

    public int $tries = 5;

    /** Mantém o job único enquanto as novas tentativas estiverem na fila. */
    public int $uniqueFor = 3600;

    public int $timeout;

    public function __construct(
        public SeiProcesso|Model $alvo,
        public Documento $documento,
        public string $chave,
        public ?string $conexao = null,
    ) {
        $this->timeout = $this->timeoutDaConexao();
    }

    public function uniqueId(): string
    {
        return $this->conexaoResolvida().':'.$this->chave;
    }

    public function handle(SeiManager $sei): void
    {
        $this->executarNoSei(fn () => $sei->integracao($this->conexao)->incluirDocumento($this->alvo, $this->documento, $this->chave));
    }
}

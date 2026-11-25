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
use MonteiroTi\Sei\Dto\Procedimento;
use MonteiroTi\Sei\Laravel\Jobs\Concerns\TrataFalhasSei;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTi\Sei\Options\GerarProcedimentoOptions;

/**
 * Gera o processo em fila, no máximo uma vez por chave.
 *
 * Falhas seguras (a requisição não chegou ao SEI, circuito aberto, lock ocupado) voltam à fila
 * com backoff. Em caso de dúvida a operação vira "incerta", o job falha e precisa de
 * conferência (sei:operacoes). Erros de negócio falham na hora. Para arquivos grandes,
 * prefira Documento::externo(idArquivo: ...) em vez de bytes no payload da fila.
 */
class GerarProcessoSeiJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TrataFalhasSei;

    public int $tries = 5;

    /** Mantém o job único enquanto as novas tentativas estiverem na fila. */
    public int $uniqueFor = 3600;

    public int $timeout;

    /**
     * @param  list<Documento>  $documentos
     */
    public function __construct(
        public Model $vinculavel,
        public Procedimento $procedimento,
        public array $documentos = [],
        public ?GerarProcedimentoOptions $options = null,
        public ?string $conexao = null,
        public ?string $chave = null,
    ) {
        $this->timeout = $this->timeoutDaConexao();
    }

    public function uniqueId(): string
    {
        return $this->chave ?? $this->vinculavel->getMorphClass().':'.$this->vinculavel->getKey().':gerarProcedimento:'.$this->conexaoResolvida();
    }

    public function handle(SeiManager $sei): void
    {
        $this->executarNoSei(fn () => $sei->integracao($this->conexao)->gerarProcesso($this->vinculavel, $this->procedimento, $this->documentos, $this->options, $this->chave));
    }
}

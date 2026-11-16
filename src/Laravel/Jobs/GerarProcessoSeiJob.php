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
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTi\Sei\Options\GerarProcedimentoOptions;

/**
 * Gera o processo em fila. Sem retry automático: em caso de dúvida a operação vira "incerta"
 * e precisa de conferência (sei:operacoes). Para arquivos grandes, prefira Documento::externo(idArquivo: ...)
 * em vez de bytes no payload da fila.
 */
class GerarProcessoSeiJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

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
        $this->timeout = (int) config('sei.connections.'.($conexao ?? config('sei.default', 'default')).'.timeout', 60) + 60;
    }

    public function uniqueId(): string
    {
        return $this->chave ?? $this->vinculavel->getMorphClass().':'.$this->vinculavel->getKey().':gerarProcedimento:'.($this->conexao ?? 'default');
    }

    public function handle(SeiManager $sei): void
    {
        $sei->integracao($this->conexao)->gerarProcesso($this->vinculavel, $this->procedimento, $this->documentos, $this->options, $this->chave);
    }
}

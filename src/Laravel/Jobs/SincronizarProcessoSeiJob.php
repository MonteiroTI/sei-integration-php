<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;
use MonteiroTi\Sei\Laravel\SeiManager;

/**
 * Sincroniza um processo. Consultas são idempotentes: falhas de rede são repetidas.
 */
class SincronizarProcessoSeiJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $uniqueFor = 600;

    public function __construct(public SeiProcesso $processo) {}

    public function uniqueId(): string
    {
        return 'sei:sincronizar:'.$this->processo->getKey();
    }

    public function handle(SeiManager $sei): void
    {
        $sei->sincronizador()->processo($this->processo);
    }
}

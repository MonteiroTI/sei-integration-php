<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Console;

use Illuminate\Console\Command;
use MonteiroTi\Sei\Core\Exceptions\SeiException;
use MonteiroTi\Sei\Laravel\Jobs\SincronizarProcessoSeiJob;
use MonteiroTi\Sei\Laravel\Models\SeiModel;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;
use MonteiroTi\Sei\Laravel\SeiManager;

final class SincronizarCommand extends Command
{
    protected $signature = 'sei:sincronizar
        {--conexao= : Só processos desta conexão}
        {--limite= : Processos por execução (padrão: sei.sincronizacao.limite)}
        {--processo= : Sincroniza só este processo (número do processo)}
        {--agora : Executa aqui, sem enfileirar}';

    protected $description = 'Consulta no SEI os processos vinculados com sincronização vencida e dispara eventos de mudança';

    public function handle(SeiManager $manager): int
    {
        $sincronizador = $manager->sincronizador();
        $conexao = $this->option('conexao');
        $conexao = is_string($conexao) && $conexao !== '' ? $conexao : null;
        $protocolo = $this->option('processo');

        if (is_string($protocolo) && $protocolo !== '') {
            $classe = SeiModel::classe('processo', SeiProcesso::class);
            $processos = $classe::query()
                ->where('protocolo_formatado', $protocolo)
                ->when($conexao !== null, fn ($q) => $q->where('conexao', $conexao))
                ->get();
        } else {
            $limite = $this->option('limite');
            $limite = is_numeric($limite) ? (int) $limite : (int) config('sei.sincronizacao.limite', 100);
            $processos = $sincronizador->pendentes($conexao, max(1, $limite));
        }

        if ($processos->isEmpty()) {
            $this->components->info('Nenhum processo a sincronizar.');

            return self::SUCCESS;
        }

        $fila = config('sei.sincronizacao.fila');
        $falhas = 0;

        foreach ($processos->all() as $processo) {
            if (! $processo instanceof SeiProcesso) {
                continue;
            }

            if (! $this->option('agora')) {
                SincronizarProcessoSeiJob::dispatch($processo)->onQueue(is_string($fila) ? $fila : null);

                continue;
            }

            try {
                $sincronizador->processo($processo);
            } catch (SeiException $e) {
                $falhas++;
                $this->components->warn("{$processo->protocolo_formatado}: {$manager->redactor()->text($e->getMessage())}");
            }
        }

        $this->components->info(sprintf(
            '%d processo(s) %s.',
            $processos->count(),
            $this->option('agora') ? 'sincronizado(s)'.($falhas > 0 ? ", {$falhas} com falha" : '') : 'enfileirado(s)',
        ));

        return $falhas > 0 ? self::FAILURE : self::SUCCESS;
    }
}

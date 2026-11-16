<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Console;

use Illuminate\Console\Command;
use MonteiroTi\Sei\Core\Exceptions\SeiException;
use MonteiroTi\Sei\Laravel\Models\SeiModel;
use MonteiroTi\Sei\Laravel\Models\SeiOperacao;
use MonteiroTi\Sei\Laravel\Models\StatusOperacao;
use MonteiroTi\Sei\Laravel\SeiManager;

final class ResolverOperacaoCommand extends Command
{
    protected $signature = 'sei:operacoes:resolver
        {chave : Chave da operação}
        {--protocolo= : gerarProcedimento executado: número do processo encontrado no SEI}
        {--documento= : incluirDocumento executado: número do documento encontrado no SEI}
        {--concluida : A operação foi executada no SEI (sem vínculo a criar)}
        {--descartar : A operação NÃO foi executada no SEI: libera nova tentativa}';

    protected $description = 'Resolve manualmente uma operação incerta após conferência no SEI';

    public function handle(SeiManager $manager): int
    {
        $classe = SeiModel::classe('operacao', SeiOperacao::class);
        $chave = $this->argument('chave');
        $operacao = is_string($chave) ? $classe::query()->where('chave', $chave)->first() : null;

        if (! $operacao instanceof SeiOperacao) {
            $this->components->error('Operação não encontrada.');

            return self::FAILURE;
        }

        $protocolo = $this->option('protocolo');
        $protocolo = is_string($protocolo) ? $protocolo : '';
        $documento = $this->option('documento');
        $documento = is_string($documento) ? $documento : '';
        $escolhidas = array_keys(array_filter([
            'protocolo' => $protocolo !== '',
            'documento' => $documento !== '',
            'concluida' => (bool) $this->option('concluida'),
            'descartar' => (bool) $this->option('descartar'),
        ]));
        if (count($escolhidas) !== 1) {
            $this->components->error('Informe exatamente uma opção: --protocolo, --documento, --concluida ou --descartar.');

            return self::INVALID;
        }

        if ($operacao->status === StatusOperacao::Concluida) {
            $this->components->warn('A operação já está concluída.');

            return self::SUCCESS;
        }

        $integracao = $manager->integracao($operacao->conexao);

        try {
            match ($escolhidas[0]) {
                'protocolo' => $this->exigir($operacao, 'gerarProcedimento')
                    && $integracao->resolverComProcesso($operacao, $protocolo),
                'documento' => $this->exigir($operacao, 'incluirDocumento')
                    && $integracao->resolverComDocumento($operacao, $documento),
                'concluida' => $operacao->forceFill(['status' => StatusOperacao::Concluida, 'concluida_em' => now(), 'erro' => null])->save(),
                'descartar' => $operacao->forceFill(['status' => StatusOperacao::Descartada])->save(),
            };
        } catch (SeiException|\InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Operação %s marcada como %s.', $operacao->chave, $operacao->refresh()->status->value));

        return self::SUCCESS;
    }

    private function exigir(SeiOperacao $operacao, string $esperada): bool
    {
        if ($operacao->operacao !== $esperada) {
            throw new \InvalidArgumentException("Esta opção só vale para {$esperada}; a operação é {$operacao->operacao}.");
        }

        return true;
    }
}

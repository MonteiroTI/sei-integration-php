<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Console;

use Illuminate\Console\Command;
use MonteiroTi\Sei\Laravel\Models\SeiModel;
use MonteiroTi\Sei\Laravel\Models\SeiOperacao;
use MonteiroTi\Sei\Laravel\Models\StatusOperacao;

final class OperacoesCommand extends Command
{
    protected $signature = 'sei:operacoes
        {--status=incerta : incerta, falhou, executando, concluida, descartada ou todas}
        {--limite=50 : Quantidade máxima de registros}';

    protected $description = 'Lista operações do SEI registradas no controle de idempotência (padrão: as incertas)';

    public function handle(): int
    {
        $status = $this->option('status');
        if (! is_string($status) || ($status !== 'todas' && StatusOperacao::tryFrom($status) === null)) {
            $this->components->error('Status inválido.');

            return self::INVALID;
        }

        $classe = SeiModel::classe('operacao', SeiOperacao::class);
        $registros = $classe::query()
            ->when($status !== 'todas', fn ($q) => $q->where('status', $status))
            ->latest('updated_at')
            ->limit(max(1, (int) $this->option('limite')))
            ->get();

        if ($registros->isEmpty()) {
            $this->components->info('Nenhuma operação encontrada.');

            return self::SUCCESS;
        }

        $linhas = [];
        foreach ($registros->all() as $o) {
            if (! $o instanceof SeiOperacao) {
                continue;
            }
            $linhas[] = [
                $o->chave,
                $o->operacao,
                $o->status->value,
                $o->tentativas,
                $o->vinculavel_type !== null ? class_basename($o->vinculavel_type).' #'.$o->vinculavel_id : '',
                (string) $o->updated_at,
                mb_strimwidth((string) $o->erro, 0, 80, '…'),
            ];
        }

        $this->table(['Chave', 'Operação', 'Status', 'Tentativas', 'Vínculo', 'Atualizada', 'Erro'], $linhas);

        if ($status === StatusOperacao::Incerta->value) {
            $this->line('Confira cada uma no SEI (a Observação do processo traz o identificador do sistema) e resolva com:');
            $this->line('  php artisan sei:operacoes:resolver "<chave>" --protocolo=<processo> | --documento=<documento> | --concluida | --descartar');
        }

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Integracao;

use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Database\Eloquent\Model;
use MonteiroTi\Sei\Core\Exceptions\ConnectionException;
use MonteiroTi\Sei\Core\Exceptions\InvalidArgumentException;
use MonteiroTi\Sei\Core\Exceptions\SeiException;
use MonteiroTi\Sei\Core\Support\Redactor;
use MonteiroTi\Sei\Laravel\Models\SeiModel;
use MonteiroTi\Sei\Laravel\Models\SeiOperacao;
use MonteiroTi\Sei\Laravel\Models\StatusOperacao;
use MonteiroTi\Sei\Operations\SeiOperations;

/**
 * Executa operações no SEI no máximo uma vez por chave.
 *
 * - concluída: devolve o resultado salvo, sem chamar o SEI;
 * - falhou/descartada: executa de novo;
 * - incerta: lança OperacaoIncertaException (conferência manual);
 * - executando: outra execução tem o lock (OperacaoEmAndamentoException); se o lock expirou,
 *   a execução anterior morreu no meio e a operação vira incerta (se não for idempotente).
 */
final class Idempotencia
{
    public function __construct(
        private readonly LockProvider $locks,
        private readonly Redactor $redactor,
        private readonly int $lockSegundos = 300,
    ) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $callback  Deve conter apenas a chamada ao SEI
     * @return T
     */
    public function executar(string $chave, string $conexao, string $operacao, Closure $callback, ?Model $vinculavel = null): mixed
    {
        if ($chave === '' || strlen($chave) > 191) {
            throw new InvalidArgumentException('A chave de idempotência deve ter entre 1 e 191 caracteres.');
        }

        $lock = $this->locks->lock('sei:operacao:'.sha1($chave), $this->lockSegundos);
        if (! $lock->get()) {
            throw new OperacaoEmAndamentoException($chave, $operacao);
        }

        try {
            $classe = SeiModel::classe('operacao', SeiOperacao::class);
            /** @var SeiOperacao $registro */
            $registro = $classe::query()->firstOrNew(['chave' => $chave]);
            $idempotente = SeiOperations::idempotente($operacao);

            if ($registro->exists) {
                if ($registro->operacao !== $operacao) {
                    throw new InvalidArgumentException("A chave \"{$chave}\" já foi usada para a operação {$registro->operacao}.");
                }

                switch ($registro->status) {
                    case StatusOperacao::Concluida:
                        return $registro->lerResultado();
                    case StatusOperacao::Incerta:
                        throw new OperacaoIncertaException($chave, $operacao);
                    case StatusOperacao::Executando:
                        // Temos o lock: a execução anterior terminou sem registrar o resultado.
                        if (! $idempotente) {
                            $registro->status = StatusOperacao::Incerta;
                            $registro->erro = 'Execução anterior interrompida sem registrar o resultado.';
                            $registro->save();

                            throw new OperacaoIncertaException($chave, $operacao);
                        }
                        break;
                    default:
                        break;
                }
            }

            $registro->fill([
                'conexao' => $conexao,
                'operacao' => $operacao,
                'status' => StatusOperacao::Executando,
                'erro' => null,
                'iniciada_em' => now(),
                'concluida_em' => null,
            ]);
            $registro->tentativas = ($registro->tentativas ?? 0) + 1;
            if ($vinculavel !== null) {
                $registro->vinculavel()->associate($vinculavel);
            }
            $registro->save();

            try {
                $resultado = $callback();
            } catch (\Throwable $e) {
                $incerta = ! $idempotente && match (true) {
                    $e instanceof ConnectionException => $e->possivelmenteEnviada,
                    $e instanceof SeiException => false,
                    default => true, // erro inesperado: não sabemos se o SEI foi chamado
                };

                $registro->status = $incerta ? StatusOperacao::Incerta : StatusOperacao::Falhou;
                $registro->erro = $this->redactor->text(mb_substr($e::class.': '.$e->getMessage(), 0, 2000));
                $registro->save();

                if ($incerta) {
                    throw new OperacaoIncertaException($chave, $operacao, $e);
                }

                throw $e;
            }

            $registro->status = StatusOperacao::Concluida;
            $registro->guardarResultado($resultado);
            $registro->concluida_em = now();
            $registro->save();

            return $resultado;
        } finally {
            $lock->release();
        }
    }

    public function registro(string $chave): ?SeiOperacao
    {
        $classe = SeiModel::classe('operacao', SeiOperacao::class);

        return $classe::query()->where('chave', $chave)->first();
    }
}

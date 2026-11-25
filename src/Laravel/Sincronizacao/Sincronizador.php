<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Sincronizacao;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use MonteiroTi\Sei\Core\Exceptions\ConnectionException;
use MonteiroTi\Sei\Core\Exceptions\FaultException;
use MonteiroTi\Sei\Core\Support\Redactor;
use MonteiroTi\Sei\Dto\Andamento;
use MonteiroTi\Sei\Dto\Assinatura;
use MonteiroTi\Sei\Enum\EstadoPublicacao;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\DocumentoSeiAssinado;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\DocumentoSeiPublicado;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\NovoAndamentoSei;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\ProcessoSeiConcluido;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\ProcessoSeiReaberto;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\ProcessoSeiTramitado;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\PublicacaoSeiAgendada;
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\SincronizacaoSeiFalhou;
use MonteiroTi\Sei\Laravel\Models\SeiDocumento;
use MonteiroTi\Sei\Laravel\Models\SeiModel;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTi\Sei\Options\ConsultaDocumentoOptions;
use MonteiroTi\Sei\Options\ConsultaProcedimentoOptions;
use MonteiroTi\Sei\SeiClient;
use Psr\Log\LoggerInterface;

/**
 * Compara o estado salvo dos processos/documentos com o SEI e dispara eventos de mudança.
 *
 * O WebService do SEI não notifica o sistema cliente: este é o mecanismo de polling.
 * A primeira sincronização de cada processo só registra o estado (sem eventos).
 */
final class Sincronizador
{
    /**
     * @param  array<string, mixed>  $config  sei.sincronizacao
     */
    public function __construct(
        private readonly SeiManager $manager,
        private readonly Dispatcher $events,
        private readonly Redactor $redactor,
        private readonly array $config = [],
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Processos com sincronização vencida, mais antigos primeiro.
     *
     * @return Collection<int, SeiProcesso>
     */
    public function pendentes(?string $conexao = null, int $limite = 100): Collection
    {
        $classe = SeiModel::classe('processo', SeiProcesso::class);

        $registros = $classe::query()
            ->where('sincronizar', true)
            ->where(fn ($q) => $q->whereNull('proxima_sincronizacao_em')->orWhere('proxima_sincronizacao_em', '<=', now()))
            ->when($conexao !== null, fn ($q) => $q->where('conexao', $conexao))
            ->orderBy('proxima_sincronizacao_em')
            ->limit($limite)
            ->get();

        $processos = [];
        foreach ($registros->all() as $registro) {
            if ($registro instanceof SeiProcesso) {
                $processos[] = $registro;
            }
        }

        return new Collection($processos);
    }

    /**
     * Sincroniza o processo e os documentos vinculados.
     *
     * @throws ConnectionException falha transitória: o job pode tentar de novo
     */
    public function processo(SeiProcesso $processo): void
    {
        if (! $processo->sincronizar || $processo->sigiloso) {
            return;
        }

        $cliente = $this->cliente($processo);

        try {
            $consulta = $cliente->processos()->consultar(
                $processo->protocolo_formatado,
                new ConsultaProcedimentoOptions(andamentoConclusao: true, ultimoAndamento: true, unidadesProcedimentoAberto: true),
            );
        } catch (FaultException $e) {
            $this->desligar($processo, $e);

            return;
        }

        $unidades = array_map(fn ($u) => [
            'id' => $u->unidade?->idUnidade,
            'sigla' => $u->unidade?->sigla,
            'usuario' => $u->usuarioAtribuicao?->sigla,
        ], $consulta->unidadesProcedimentoAberto);

        $primeira = $processo->unidades_abertas === null;
        $antes = $processo->idsUnidadesAbertas();
        $depois = array_values(array_filter(array_column($unidades, 'id')));
        $abertoAntes = $processo->aberto;
        $aberto = $depois !== [];
        $ultimo = $consulta->ultimoAndamento;

        $eventos = [];
        if (! $primeira) {
            if ($ultimo?->idAndamento !== null && $ultimo->idAndamento !== $processo->ultimo_andamento_id) {
                foreach ($this->andamentosIntermediarios($cliente, $processo, $ultimo->idAndamento) as $andamento) {
                    $eventos[] = new NovoAndamentoSei($processo, $andamento, intermediario: true);
                }
                $eventos[] = new NovoAndamentoSei($processo, $ultimo);
            }
            $a = $antes;
            $d = $depois;
            sort($a);
            sort($d);
            if ($a !== $d) {
                $eventos[] = new ProcessoSeiTramitado($processo, $antes, $depois);
            }
            if ($abertoAntes && ! $aberto) {
                $eventos[] = new ProcessoSeiConcluido($processo, $consulta->andamentoConclusao);
            } elseif (! $abertoAntes && $aberto) {
                $eventos[] = new ProcessoSeiReaberto($processo);
            }
        }

        $processo->fill([
            'unidades_abertas' => $unidades,
            'aberto' => $aberto,
            'id_procedimento' => $consulta->idProcedimento ?? $processo->id_procedimento,
            'link_acesso' => $consulta->linkAcesso ?? $processo->link_acesso,
            'ultimo_andamento_id' => $ultimo->idAndamento ?? $processo->ultimo_andamento_id,
            'ultimo_andamento_em' => $ultimo->dataHora ?? $processo->ultimo_andamento_em,
            'concluido_em' => $aberto ? null : ($consulta->andamentoConclusao->dataHora ?? $processo->concluido_em ?? now()),
            'sincronizado_em' => now(),
            'erro_sincronizacao' => null,
            'proxima_sincronizacao_em' => now()->addSeconds($this->intervalo($aberto)),
        ]);
        $processo->save();

        foreach ($eventos as $evento) {
            $this->events->dispatch($evento);
        }

        foreach ($processo->documentos()->where('sincronizar', true)->get() as $documento) {
            if ($aberto || $documento->publicacao_estado === EstadoPublicacao::Agendado->value) {
                $this->documento($documento, $processo);
            }
        }
    }

    /** Atualiza assinaturas e publicação do documento. */
    public function documento(SeiDocumento $documento, ?SeiProcesso $processo = null): void
    {
        $processo ??= $documento->processo;
        if ($processo === null) {
            return;
        }

        try {
            $consulta = $this->cliente($processo)->documentos()->consultar(
                $documento->documento_formatado,
                new ConsultaDocumentoOptions(assinaturas: true, publicacao: true),
            );
        } catch (FaultException $e) {
            $this->desligar($documento, $e);

            return;
        }

        $eventos = [];

        $conhecidas = array_map(fn (array $a) => ($a['sigla'] ?? '').'|'.($a['data_hora'] ?? ''), $documento->assinaturas ?? []);
        $novas = array_values(array_filter(
            $consulta->assinaturas,
            fn (Assinatura $a) => ! in_array($a->sigla.'|'.$a->dataHora?->format('Y-m-d H:i:s'), $conhecidas, true),
        ));
        if ($novas !== []) {
            $eventos[] = new DocumentoSeiAssinado($documento, $novas);
        }

        $publicacao = $consulta->publicacao;
        $estadoAntes = $documento->publicacao_estado;
        if ($publicacao?->estado !== null && $publicacao->estado->value !== $estadoAntes) {
            $eventos[] = $publicacao->estado === EstadoPublicacao::Publicado
                ? new DocumentoSeiPublicado($documento, $publicacao)
                : new PublicacaoSeiAgendada($documento, $publicacao);
        }

        $assinaturas = array_map(fn (Assinatura $a) => [
            'nome' => $a->nome,
            'sigla' => $a->sigla,
            'cargo' => $a->cargoFuncao,
            'data_hora' => $a->dataHora?->format('Y-m-d H:i:s'),
        ], $consulta->assinaturas);
        $ultimaAssinatura = array_reduce(
            $consulta->assinaturas,
            fn ($max, Assinatura $a) => $a->dataHora !== null && ($max === null || $a->dataHora > $max) ? $a->dataHora : $max,
        );

        $documento->fill([
            'assinaturas' => $assinaturas,
            'assinado_em' => $ultimaAssinatura ?? $documento->assinado_em,
            'id_publicacao' => $publicacao->idPublicacao ?? $documento->id_publicacao,
            'publicacao_estado' => $publicacao?->estado->value ?? $estadoAntes,
            'publicado_em' => $publicacao->dataPublicacao ?? $documento->publicado_em,
            'link_acesso' => $consulta->linkAcesso ?? $documento->link_acesso,
            'sincronizado_em' => now(),
        ]);
        $documento->save();

        foreach ($eventos as $evento) {
            $this->events->dispatch($evento);
        }
    }

    /**
     * Andamentos das tarefas configuradas (sei.sincronizacao.andamentos.tarefas) entre o último
     * andamento conhecido e o atual, em ordem. O listarAndamentos do SEI exige um filtro, por isso
     * só as tarefas configuradas são buscadas. Sem tarefas, sem a operação liberada ou se o SEI
     * recusar a consulta, devolve [] (vale só o último andamento).
     *
     * @return list<Andamento>
     */
    private function andamentosIntermediarios(SeiClient $cliente, SeiProcesso $processo, string $idUltimo): array
    {
        $config = $this->config['andamentos'] ?? [];
        $tarefas = is_array($config) && is_array($config['tarefas'] ?? null) ? array_values($config['tarefas']) : [];
        $anterior = $processo->ultimo_andamento_id;

        if ($tarefas === [] || $anterior === null || ! $cliente->config()->permite('listarAndamentos')) {
            return [];
        }

        try {
            $andamentos = $cliente->andamentos()->listar(
                $processo->protocolo_formatado,
                tarefas: array_map(fn ($t) => is_int($t) ? $t : (string) $t, $tarefas),
                retornarAtributos: is_array($config) && (bool) ($config['atributos'] ?? false),
            );
        } catch (FaultException $e) {
            $this->logger?->warning('SEI: não foi possível listar os andamentos intermediários; vale só o último.', [
                'processo' => $processo->protocolo_formatado,
                'message' => $this->redactor->text($e->getMessage()),
            ]);

            return [];
        }

        $intermediarios = array_values(array_filter(
            $andamentos,
            fn (Andamento $a) => $a->idAndamento !== null
                && self::compararIds($a->idAndamento, $anterior) > 0
                && self::compararIds($a->idAndamento, $idUltimo) < 0,
        ));
        usort($intermediarios, fn (Andamento $a, Andamento $b) => self::compararIds((string) $a->idAndamento, (string) $b->idAndamento));

        return $intermediarios;
    }

    /** Compara IDs numéricos do SEI como string (passam de 32 bits). */
    private static function compararIds(string $a, string $b): int
    {
        $a = ltrim($a, '0');
        $b = ltrim($b, '0');

        return strlen($a) <=> strlen($b) ?: strcmp($a, $b) <=> 0;
    }

    private function cliente(SeiProcesso $processo): SeiClient
    {
        $mapa = $this->config['conexao'] ?? null;
        $conexao = match (true) {
            is_string($mapa) && $mapa !== '' => $mapa,
            is_array($mapa) && isset($mapa[$processo->conexao]) && is_string($mapa[$processo->conexao]) => $mapa[$processo->conexao],
            default => $processo->conexao,
        };

        $client = $this->manager->connection($conexao);

        return $processo->id_unidade_geradora !== null ? $client->naUnidade($processo->id_unidade_geradora) : $client;
    }

    private function desligar(SeiProcesso|SeiDocumento $registro, FaultException $e): void
    {
        $registro->sincronizar = false;
        if ($registro instanceof SeiProcesso) {
            $registro->erro_sincronizacao = $this->redactor->text(mb_substr($e->getMessage(), 0, 2000));
            $registro->proxima_sincronizacao_em = null;
        }
        $registro->sincronizado_em = now();
        $registro->save();

        $this->events->dispatch(new SincronizacaoSeiFalhou($registro, $e));
    }

    private function intervalo(bool $aberto): int
    {
        $chave = $aberto ? 'intervalo' : 'intervalo_concluidos';
        $padrao = $aberto ? 900 : 86400;
        $valor = $this->config[$chave] ?? $padrao;

        return max(60, is_numeric($valor) ? (int) $valor : $padrao);
    }
}

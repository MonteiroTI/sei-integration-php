<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Integracao;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use MonteiroTi\Sei\Core\Exceptions\InvalidArgumentException;
use MonteiroTi\Sei\Core\Exceptions\SeiException;
use MonteiroTi\Sei\Dto\Documento;
use MonteiroTi\Sei\Dto\Procedimento;
use MonteiroTi\Sei\Dto\RetornoGeracaoProcedimento;
use MonteiroTi\Sei\Dto\RetornoInclusaoDocumento;
use MonteiroTi\Sei\Enum\NivelAcesso;
use MonteiroTi\Sei\Laravel\Models\SeiDocumento;
use MonteiroTi\Sei\Laravel\Models\SeiModel;
use MonteiroTi\Sei\Laravel\Models\SeiOperacao;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;
use MonteiroTi\Sei\Laravel\Models\StatusOperacao;
use MonteiroTi\Sei\Options\EnviarProcessoOptions;
use MonteiroTi\Sei\Options\GerarProcedimentoOptions;
use MonteiroTi\Sei\SeiClient;

/**
 * Operações de alto nível que combinam idempotência, vínculo no banco e rastreabilidade.
 *
 * Uso: Sei::integracao()->gerarProcesso($requerimento, $procedimento, [$documento]).
 */
final class SeiIntegracao
{
    /**
     * @param  array<string, mixed>  $config  sei.integracao
     */
    public function __construct(
        private readonly SeiClient $client,
        private readonly string $conexao,
        private readonly Idempotencia $idempotencia,
        private readonly array $config = [],
    ) {}

    /** Cópia operando em outra unidade. */
    public function naUnidade(string $idUnidade): self
    {
        return new self($this->client->naUnidade($idUnidade), $this->conexao, $this->idempotencia, $this->config);
    }

    /**
     * Gera o processo uma única vez para o registro do sistema e cria os vínculos.
     *
     * A chave padrão é "{conexao}:{tipo}:{id}:gerarProcedimento": um registro gera um processo por conexão.
     * Informe outra chave se o mesmo registro puder gerar vários processos.
     *
     * @param  list<Documento>  $documentos
     *
     * @throws OperacaoIncertaException quando um timeout impede saber se o processo foi criado
     */
    public function gerarProcesso(
        Model $vinculavel,
        Procedimento $procedimento,
        array $documentos = [],
        ?GerarProcedimentoOptions $options = null,
        ?string $chave = null,
    ): SeiProcesso {
        $chave ??= $this->chave($vinculavel, 'gerarProcedimento');
        $procedimento = $this->comIdentificador($procedimento, $vinculavel);

        /** @var RetornoGeracaoProcedimento $retorno */
        $retorno = $this->idempotencia->executar(
            $chave,
            $this->conexao,
            'gerarProcedimento',
            fn () => $this->client->processos()->gerar($procedimento, $documentos, $options),
            $vinculavel,
        );

        $processo = $this->registrarProcesso($retorno, $vinculavel, $procedimento->idTipoProcedimento, $procedimento->nivelAcesso);

        foreach ($retorno->documentos as $i => $doc) {
            $this->registrarDocumento($processo, $doc, $documentos[$i] ?? null);
        }

        $this->lancarRastreabilidade($processo, $chave, 'Processo gerado', $vinculavel);

        return $processo;
    }

    /**
     * Inclui um documento uma única vez (por chave) e cria o vínculo.
     *
     * @param  SeiProcesso|Model  $alvo  Vínculo do processo ou model do sistema com HasSeiProcesso
     */
    public function incluirDocumento(SeiProcesso|Model $alvo, Documento $documento, string $chave): SeiDocumento
    {
        $processo = $this->processoDe($alvo);
        $chave = $this->conexao.':'.$chave;

        /** @var RetornoInclusaoDocumento $retorno */
        $retorno = $this->idempotencia->executar(
            $chave,
            $this->conexao,
            'incluirDocumento',
            fn () => $this->client->documentos()->incluir($documento->noProcesso($processo->protocolo_formatado)),
            $processo->vinculavel,
        );

        $registro = $this->registrarDocumento($processo, $retorno, $documento);
        $this->lancarRastreabilidade($processo, $chave, sprintf('Documento %s incluído', $retorno->documentoFormatado), $processo->vinculavel);

        return $registro;
    }

    /**
     * Envia o processo uma única vez por chave. A próxima sincronização atualiza as unidades.
     *
     * @param  list<string>  $unidadesDestino
     */
    public function enviarProcesso(SeiProcesso $processo, array $unidadesDestino, string $chave, ?EnviarProcessoOptions $options = null): void
    {
        $this->idempotencia->executar(
            $this->conexao.':'.$chave,
            $this->conexao,
            'enviarProcesso',
            function () use ($processo, $unidadesDestino, $options) {
                $this->client->processos()->enviar($processo->protocolo_formatado, $unidadesDestino, $options);

                return true;
            },
            $processo->vinculavel,
        );

        $this->sincronizarEmBreve($processo);
    }

    public function concluirProcesso(SeiProcesso $processo, ?string $chave = null): void
    {
        $this->idempotencia->executar(
            $this->conexao.':'.($chave ?? 'concluirProcesso:'.$processo->id.':'.Str::uuid()),
            $this->conexao,
            'concluirProcesso',
            function () use ($processo) {
                $this->client->processos()->concluir($processo->protocolo_formatado);

                return true;
            },
            $processo->vinculavel,
        );

        $this->sincronizarEmBreve($processo);
    }

    /**
     * Resolve uma operação incerta de gerarProcedimento informando o processo encontrado no SEI.
     */
    public function resolverComProcesso(SeiOperacao $operacao, string $protocolo): SeiProcesso
    {
        $consulta = $this->client->processos()->consultar($protocolo);
        $retorno = new RetornoGeracaoProcedimento($consulta->idProcedimento, $consulta->procedimentoFormatado ?? $protocolo, $consulta->linkAcesso);

        $this->concluirManualmente($operacao, $retorno);
        $vinculavel = $operacao->vinculavel;

        return $this->registrarProcesso($retorno, $vinculavel instanceof Model ? $vinculavel : null, $consulta->tipoProcedimento?->idTipoProcedimento, $consulta->nivelAcessoLocal);
    }

    /**
     * Resolve uma operação incerta de incluirDocumento informando o documento encontrado no SEI.
     */
    public function resolverComDocumento(SeiOperacao $operacao, string $protocoloDocumento): SeiDocumento
    {
        $consulta = $this->client->documentos()->consultar($protocoloDocumento);
        $processo = $this->registrarProcesso(
            new RetornoGeracaoProcedimento($consulta->idProcedimento, (string) $consulta->procedimentoFormatado),
            $operacao->vinculavel instanceof Model ? $operacao->vinculavel : null,
        );
        $retorno = new RetornoInclusaoDocumento($consulta->idDocumento, $consulta->documentoFormatado ?? $protocoloDocumento, $consulta->linkAcesso);

        $this->concluirManualmente($operacao, $retorno);

        return $this->registrarDocumento($processo, $retorno, null, $consulta->serie?->idSerie);
    }

    public function client(): SeiClient
    {
        return $this->client;
    }

    private function concluirManualmente(SeiOperacao $operacao, mixed $resultado): void
    {
        $operacao->status = StatusOperacao::Concluida;
        $operacao->guardarResultado($resultado);
        $operacao->erro = null;
        $operacao->concluida_em = now();
        $operacao->save();
    }

    private function registrarProcesso(RetornoGeracaoProcedimento $retorno, ?Model $vinculavel, ?string $idTipo = null, ?NivelAcesso $nivel = null): SeiProcesso
    {
        $classe = SeiModel::classe('processo', SeiProcesso::class);
        /** @var SeiProcesso $processo */
        $processo = $classe::query()->firstOrNew([
            'conexao' => $this->conexao,
            'protocolo_formatado' => (string) $retorno->procedimentoFormatado,
        ]);

        $sigiloso = $nivel === NivelAcesso::Sigiloso;
        $processo->fill(array_filter([
            'id_procedimento' => $retorno->idProcedimento,
            'link_acesso' => $retorno->linkAcesso,
            'id_tipo_procedimento' => $idTipo,
            'nivel_acesso' => $nivel?->value,
        ], fn ($v) => $v !== null));

        if (! $processo->exists) {
            $processo->id_unidade_geradora = $this->client->config()->idUnidade;
            $processo->sigiloso = $sigiloso;
            // Consultas do WebService não retornam processos sigilosos: não há o que sincronizar.
            $processo->sincronizar = ! $sigiloso;
            $processo->proxima_sincronizacao_em = $sigiloso ? null : now();
        }
        if ($vinculavel !== null && $processo->vinculavel_type === null) {
            $processo->vinculavel()->associate($vinculavel);
        }
        $processo->save();

        return $processo;
    }

    private function registrarDocumento(SeiProcesso $processo, RetornoInclusaoDocumento $retorno, ?Documento $documento, ?string $idSerie = null): SeiDocumento
    {
        $classe = SeiModel::classe('documento', SeiDocumento::class);
        /** @var SeiDocumento $registro */
        $registro = $classe::query()->firstOrNew([
            'conexao' => $this->conexao,
            'documento_formatado' => (string) $retorno->documentoFormatado,
        ]);

        $registro->fill(array_filter([
            'sei_processo_id' => $processo->id,
            'id_documento' => $retorno->idDocumento,
            'link_acesso' => $retorno->linkAcesso,
            'id_serie' => $documento->idSerie ?? $idSerie,
            'tipo' => $documento?->tipo->value,
        ], fn ($v) => $v !== null));
        if (! $registro->exists) {
            $registro->sincronizar = ! $processo->sigiloso;
        }
        $registro->save();

        return $registro;
    }

    private function processoDe(SeiProcesso|Model $alvo): SeiProcesso
    {
        if ($alvo instanceof SeiProcesso) {
            return $alvo;
        }

        if (! method_exists($alvo, 'seiProcessos')) {
            throw new InvalidArgumentException(sprintf('%s não usa o trait HasSeiProcesso.', $alvo::class));
        }

        /** @var SeiProcesso|null $processo */
        $processo = $alvo->seiProcessos()->where('conexao', $this->conexao)->latest('id')->first();

        return $processo ?? throw new InvalidArgumentException(sprintf(
            '%s #%s não tem processo no SEI na conexão [%s].',
            class_basename($alvo),
            (string) $alvo->getKey(),
            $this->conexao,
        ));
    }

    private function chave(Model $vinculavel, string $operacao): string
    {
        return sprintf('%s:%s:%s:%s', $this->conexao, $vinculavel->getMorphClass(), (string) $vinculavel->getKey(), $operacao);
    }

    private function comIdentificador(Procedimento $procedimento, Model $vinculavel): Procedimento
    {
        $template = $this->config['identificador'] ?? null;
        if (! is_string($template) || $template === '') {
            return $procedimento;
        }

        $identificador = $this->preencher($template, $vinculavel);
        if ($procedimento->observacao !== null && str_contains($procedimento->observacao, $identificador)) {
            return $procedimento;
        }

        return $procedimento->with([
            'observacao' => trim(($procedimento->observacao ?? '').' '.$identificador),
        ]);
    }

    private function lancarRastreabilidade(SeiProcesso $processo, string $chave, string $acao, ?Model $vinculavel): void
    {
        $andamento = $this->config['andamento'] ?? [];
        if (! is_array($andamento) || empty($andamento['ativo'])) {
            return;
        }

        $texto = $this->preencher((string) ($andamento['texto'] ?? '{acao} pelo sistema {sistema}.'), $vinculavel, ['{acao}' => $acao]);

        try {
            $this->idempotencia->executar(
                $chave.':andamento',
                $this->conexao,
                'lancarAndamento',
                fn () => $this->client->andamentos()->atualizar($processo->protocolo_formatado, $texto),
                $vinculavel,
            );
        } catch (SeiException $e) {
            // A rastreabilidade não deve desfazer a operação principal; fica registrada no ledger.
            report($e);
        }
    }

    /**
     * @param  array<string, string>  $extras
     */
    private function preencher(string $template, ?Model $vinculavel, array $extras = []): string
    {
        return strtr($template, [
            '{sistema}' => (string) ($this->config['sistema'] ?? ''),
            '{tipo}' => $vinculavel !== null ? class_basename($vinculavel) : '',
            '{id}' => $vinculavel !== null ? (string) $vinculavel->getKey() : '',
            ...$extras,
        ]);
    }

    private function sincronizarEmBreve(SeiProcesso $processo): void
    {
        if ($processo->sincronizar) {
            $processo->proxima_sincronizacao_em = now();
            $processo->save();
        }
    }
}

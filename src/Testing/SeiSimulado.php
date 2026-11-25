<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Testing;

use DateTimeImmutable;
use MonteiroTi\Sei\Core\Exceptions\FaultException;
use MonteiroTi\Sei\Core\Exceptions\SeiException;
use MonteiroTi\Sei\Enum\NivelAcesso;

/**
 * SEI em memória para testes: gera protocolos, guarda processos, documentos e andamentos
 * e responde às consultas de forma coerente com o que foi criado.
 *
 *     $fake = Sei::fake()->simular();
 *     $processo = Sei::integracao()->gerarProcesso($requerimento, $procedimento);
 *     $fake->sei()->tramitar($processo->protocolo_formatado, '110000015');
 *
 * Cobre gerarProcedimento, incluirDocumento, consultarProcedimento, consultarDocumento,
 * enviarProcesso, concluirProcesso, reabrirProcesso, lancarAndamento, listarAndamentos,
 * listarUnidades, adicionarArquivo e adicionarConteudoArquivo. As demais operações exigem
 * resposta programada com respond(), que também tem prioridade sobre o simulador.
 *
 * Não reproduz regras de negócio do SEI (permissões, tipos, séries). Os IDs de tarefa dos
 * andamentos automáticos são ilustrativos (constantes TAREFA_*).
 */
final class SeiSimulado
{
    public const TAREFA_GERACAO = 1;

    public const TAREFA_INCLUSAO_DOCUMENTO = 2;

    public const TAREFA_ENVIO = 32;

    public const TAREFA_CONCLUSAO = 28;

    public const TAREFA_REABERTURA = 29;

    public const TAREFA_ASSINATURA = 5;

    private const OPERACOES = [
        'gerarProcedimento', 'incluirDocumento', 'consultarProcedimento', 'consultarDocumento',
        'enviarProcesso', 'concluirProcesso', 'reabrirProcesso', 'lancarAndamento', 'listarAndamentos',
        'listarUnidades', 'adicionarArquivo', 'adicionarConteudoArquivo',
    ];

    private int $seqProcesso = 0;

    private int $seqDocumento = 0;

    private int $seqAndamento = 0;

    private int $seqArquivo = 0;

    /**
     * @var array<string, array{id: string, protocolo: string, especificacao: ?string, idTipoProcedimento: ?string, nivelAcesso: int, unidadeGeradora: string, unidades: list<string>, andamentos: list<array<string, mixed>>, conclusao: ?array<string, mixed>, dataAutuacao: DateTimeImmutable}>
     */
    private array $processos = [];

    /**
     * @var array<string, array{id: string, formatado: string, processo: string, idSerie: ?string, numero: ?string, unidade: string, assinaturas: list<array<string, mixed>>, nivelAcesso: int}>
     */
    private array $documentos = [];

    /** @var array<array-key, string> */
    private array $arquivos = [];

    /**
     * @param  array<array-key, string>  $unidades  Unidades do órgão (ID => sigla), usadas em listarUnidades
     * @param  string  $prefixo  Início dos protocolos gerados (ex.: 12.1 → 12.1.000000001-1)
     */
    public function __construct(
        private readonly array $unidades = ['110000001' => 'PROTOCOLO', '110000015' => 'DIAP'],
        private readonly string $prefixo = '12.1',
    ) {}

    public function simula(string $operation): bool
    {
        return in_array($operation, self::OPERACOES, true);
    }

    /**
     * @param  array<string, mixed>  $args  Argumentos nomeados, como o transporte os recebe
     */
    public function call(string $operation, array $args): mixed
    {
        $unidade = isset($args['IdUnidade']) ? (string) $args['IdUnidade'] : (string) array_key_first($this->unidades);

        return match ($operation) {
            'gerarProcedimento' => $this->gerarProcedimento($args, $unidade),
            'incluirDocumento' => $this->incluirDocumento(self::arr($args['Documento'] ?? []), $unidade),
            'consultarProcedimento' => $this->consultarProcedimento($args),
            'consultarDocumento' => $this->consultarDocumento($args),
            'enviarProcesso' => $this->enviarProcesso($args, $unidade),
            'concluirProcesso' => $this->concluirNaUnidade($args, $unidade),
            'reabrirProcesso' => $this->reabrirNaUnidade($args, $unidade),
            'lancarAndamento' => $this->lancarAndamentoSoap($args, $unidade),
            'listarAndamentos' => $this->listarAndamentos($args),
            'listarUnidades' => Respostas::unidades($this->unidades),
            'adicionarArquivo' => $this->adicionarArquivo($args),
            'adicionarConteudoArquivo' => $this->adicionarConteudoArquivo($args),
            default => throw new SeiException(sprintf(
                'SeiSimulado: a operação "%s" não é simulada; programe a resposta com respond().',
                $operation,
            ), $operation),
        };
    }

    // ---------------------------------------------------------------------
    // Ações para os testes mudarem o estado (como se um usuário agisse no SEI)
    // ---------------------------------------------------------------------

    /**
     * Envia o processo para as unidades. Sem $manterAberto, ele sai das unidades onde estava.
     *
     * @param  string|list<string>  $unidades
     */
    public function tramitar(string $protocolo, string|array $unidades, bool $manterAberto = false): self
    {
        $p = &$this->processoRef($protocolo, 'enviarProcesso');
        $destinos = array_map('strval', (array) $unidades);
        $p['unidades'] = array_values(array_unique(array_merge($manterAberto ? $p['unidades'] : [], $destinos)));
        $p['conclusao'] = null;
        $this->registrarAndamento($p, self::TAREFA_ENVIO, 'Processo remetido para '.implode(', ', $destinos), $destinos[0] ?? $p['unidadeGeradora']);

        return $this;
    }

    /** Conclui o processo na unidade informada (ou em todas). */
    public function concluir(string $protocolo, ?string $unidade = null): self
    {
        $p = &$this->processoRef($protocolo, 'concluirProcesso');
        $p['unidades'] = $unidade === null ? [] : array_values(array_diff($p['unidades'], [$unidade]));
        $andamento = $this->registrarAndamento($p, self::TAREFA_CONCLUSAO, 'Conclusão do processo na unidade', $unidade ?? $p['unidadeGeradora']);
        if ($p['unidades'] === []) {
            $p['conclusao'] = $andamento;
        }

        return $this;
    }

    public function reabrir(string $protocolo, string $unidade): self
    {
        $p = &$this->processoRef($protocolo, 'reabrirProcesso');
        $p['unidades'] = array_values(array_unique([...$p['unidades'], $unidade]));
        $p['conclusao'] = null;
        $this->registrarAndamento($p, self::TAREFA_REABERTURA, 'Reabertura do processo na unidade', $unidade);

        return $this;
    }

    /**
     * Lança um andamento no histórico do processo.
     *
     * @return array<string, mixed> Estrutura Andamento
     */
    public function lancarAndamento(string $protocolo, int|string $tarefa = 65, ?string $descricao = null, ?string $unidade = null): array
    {
        $p = &$this->processoRef($protocolo, 'lancarAndamento');

        return $this->registrarAndamento($p, $tarefa, $descricao, $unidade ?? $p['unidadeGeradora'], $descricao === null ? [] : ['DESCRICAO' => $descricao]);
    }

    /** Assina o documento. */
    public function assinar(string $documento, string $nome = 'Fulano de Tal', ?string $sigla = null, ?string $cargo = null): self
    {
        $d = &$this->documentoRef($documento, 'consultarDocumento');
        $d['assinaturas'][] = Respostas::assinatura($nome, $sigla, $cargo, new DateTimeImmutable('+'.count($d['assinaturas']).' seconds'));
        $p = &$this->processoRef($d['processo'], 'consultarDocumento');
        $this->registrarAndamento($p, self::TAREFA_ASSINATURA, "Assinado o documento {$d['formatado']}", $d['unidade']);

        return $this;
    }

    /**
     * Estado de um processo (para asserções), ou null.
     *
     * @return array<string, mixed>|null
     */
    public function processo(string $protocolo): ?array
    {
        return $this->processos[$this->chaveProcesso($protocolo) ?? ''] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function processos(): array
    {
        return $this->processos;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function documentos(): array
    {
        return $this->documentos;
    }

    /** Conteúdo (bytes) de um arquivo enviado com adicionarArquivo/adicionarConteudoArquivo. */
    public function arquivo(string $idArquivo): ?string
    {
        return $this->arquivos[$idArquivo] ?? null;
    }

    // ---------------------------------------------------------------------
    // Operações
    // ---------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function gerarProcedimento(array $args, string $unidade): array
    {
        $procedimento = self::arr($args['Procedimento'] ?? []);
        $seq = ++$this->seqProcesso;
        $numero = str_pad((string) $seq, 9, '0', STR_PAD_LEFT);
        $protocolo = sprintf('%s.%s-%d', $this->prefixo, $numero, $seq % 10);

        $envio = array_values(array_map('strval', self::arr($args['UnidadesEnvio'] ?? [])));
        $manter = ($args['SinManterAbertoUnidade'] ?? 'N') === 'S';

        $this->processos[$protocolo] = [
            'id' => (string) preg_replace('/\D/', '', $protocolo),
            'protocolo' => $protocolo,
            'especificacao' => self::str($procedimento['Especificacao'] ?? null),
            'idTipoProcedimento' => self::str($procedimento['IdTipoProcedimento'] ?? null),
            'nivelAcesso' => (int) ($procedimento['NivelAcesso'] ?? 0),
            'unidadeGeradora' => $unidade,
            'unidades' => [$unidade],
            'andamentos' => [],
            'conclusao' => null,
            'dataAutuacao' => new DateTimeImmutable,
        ];
        $p = &$this->processos[$protocolo];
        $this->registrarAndamento($p, self::TAREFA_GERACAO, 'Processo público gerado', $unidade);

        $documentos = [];
        foreach (self::arr($args['Documentos'] ?? []) as $documento) {
            $documentos[] = $this->criarDocumento($p, self::arr($documento), $unidade);
        }

        if ($envio !== []) {
            $this->tramitar($protocolo, $envio, $manter);
        }

        return Respostas::processoGerado($protocolo, $p['id'], documentos: $documentos);
    }

    /**
     * @param  array<string, mixed>  $documento
     * @return array<string, mixed>
     */
    private function incluirDocumento(array $documento, string $unidade): array
    {
        $ref = self::str($documento['ProtocoloProcedimento'] ?? null) ?? self::str($documento['IdProcedimento'] ?? null) ?? '';
        $p = &$this->processoRef($ref, 'incluirDocumento');

        return $this->criarDocumento($p, $documento, $unidade);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function consultarProcedimento(array $args): array
    {
        $p = $this->processoRef(self::str($args['ProtocoloProcedimento'] ?? null) ?? '', 'consultarProcedimento');
        if ($p['nivelAcesso'] === NivelAcesso::Sigiloso->value) {
            throw new FaultException("Processo {$p['protocolo']} não encontrado.", 'consultarProcedimento');
        }

        $sim = fn (string $flag) => ($args[$flag] ?? 'N') === 'S';

        return Respostas::processo(
            $p['protocolo'],
            $p['id'],
            unidadesAbertas: $sim('SinRetornarUnidadesProcedimentoAberto') ? array_values(array_map(fn (string $u) => $this->unidade($u), $p['unidades'])) : [],
            ultimoAndamento: $sim('SinRetornarUltimoAndamento') ? (end($p['andamentos']) ?: null) : null,
            andamentoConclusao: $sim('SinRetornarAndamentoConclusao') ? $p['conclusao'] : null,
            andamentoGeracao: $sim('SinRetornarAndamentoGeracao') ? ($p['andamentos'][0] ?? null) : null,
            especificacao: $p['especificacao'],
            idTipoProcedimento: $p['idTipoProcedimento'],
            nivelAcesso: NivelAcesso::from($p['nivelAcesso']),
            dataAutuacao: $p['dataAutuacao'],
        );
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function consultarDocumento(array $args): array
    {
        $d = $this->documentoRef(self::str($args['ProtocoloDocumento'] ?? null) ?? '', 'consultarDocumento');

        return Respostas::documento(
            $d['formatado'],
            $d['processo'],
            $d['id'],
            idSerie: $d['idSerie'],
            numero: $d['numero'],
            assinaturas: ($args['SinRetornarAssinaturas'] ?? 'N') === 'S' ? $d['assinaturas'] : [],
            idUnidadeElaboradora: $d['unidade'],
            nivelAcesso: NivelAcesso::from($d['nivelAcesso']),
        );
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function concluirNaUnidade(array $args, string $unidade): bool
    {
        $this->concluir(self::str($args['ProtocoloProcedimento'] ?? null) ?? '', $unidade);

        return true;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function reabrirNaUnidade(array $args, string $unidade): bool
    {
        $this->reabrir(self::str($args['ProtocoloProcedimento'] ?? null) ?? '', $unidade);

        return true;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function enviarProcesso(array $args, string $unidade): bool
    {
        $protocolo = self::str($args['ProtocoloProcedimento'] ?? null) ?? '';
        $p = &$this->processoRef($protocolo, 'enviarProcesso');
        $destinos = array_values(array_map('strval', self::arr($args['UnidadesDestino'] ?? [])));
        $manter = ($args['SinManterAbertoUnidade'] ?? 'N') === 'S';

        $p['unidades'] = array_values(array_unique(array_merge(
            $manter ? $p['unidades'] : array_values(array_diff($p['unidades'], [$unidade])),
            $destinos,
        )));
        $p['conclusao'] = null;
        $this->registrarAndamento($p, self::TAREFA_ENVIO, 'Processo remetido para '.implode(', ', $destinos), $unidade);

        return true;
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function lancarAndamentoSoap(array $args, string $unidade): array
    {
        $p = &$this->processoRef(self::str($args['ProtocoloProcedimento'] ?? null) ?? '', 'lancarAndamento');
        $atributos = [];
        foreach (self::arr($args['Atributos'] ?? []) as $atributo) {
            $atributo = self::arr($atributo);
            $atributos[(string) ($atributo['Nome'] ?? '')] = self::str($atributo['Valor'] ?? null);
        }
        $tarefa = self::str($args['IdTarefa'] ?? null) ?? self::str($args['IdTarefaModulo'] ?? null) ?? '65';

        return $this->registrarAndamento($p, $tarefa, $atributos['DESCRICAO'] ?? null, $unidade, $atributos);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return list<array<string, mixed>>
     */
    private function listarAndamentos(array $args): array
    {
        $p = $this->processoRef(self::str($args['ProtocoloProcedimento'] ?? null) ?? '', 'listarAndamentos');
        $ids = array_map('strval', self::arr($args['Andamentos'] ?? []));
        $tarefas = array_map('strval', [...self::arr($args['Tarefas'] ?? []), ...self::arr($args['TarefasModulos'] ?? [])]);
        $atributos = ($args['SinRetornarAtributos'] ?? 'N') === 'S';

        $lista = [];
        foreach ($p['andamentos'] as $andamento) {
            if (in_array($andamento['IdAndamento'], $ids, true) || in_array($andamento['IdTarefa'], $tarefas, true)) {
                $lista[] = $atributos ? $andamento : ['Atributos' => []] + $andamento;
            }
        }

        return $lista;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function adicionarArquivo(array $args): string
    {
        $id = (string) (++$this->seqArquivo);
        $this->arquivos[$id] = base64_decode((string) ($args['Conteudo'] ?? ''), true) ?: '';

        return $id;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function adicionarConteudoArquivo(array $args): string
    {
        $id = (string) ($args['IdArquivo'] ?? '');
        if (! isset($this->arquivos[$id])) {
            throw new FaultException("Arquivo {$id} não encontrado.", 'adicionarConteudoArquivo');
        }
        $this->arquivos[$id] .= base64_decode((string) ($args['Conteudo'] ?? ''), true) ?: '';

        return $id;
    }

    // ---------------------------------------------------------------------
    // Internos
    // ---------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $p  Processo (por referência)
     * @param  array<string, mixed>  $documento  Estrutura Documento enviada
     * @return array<string, mixed>
     */
    private function criarDocumento(array &$p, array $documento, string $unidade): array
    {
        $seq = ++$this->seqDocumento;
        $formatado = str_pad((string) $seq, 7, '0', STR_PAD_LEFT);
        $this->documentos[$formatado] = [
            'id' => (string) (1140000000000 + $seq),
            'formatado' => $formatado,
            'processo' => $p['protocolo'],
            'idSerie' => self::str($documento['IdSerie'] ?? null),
            'numero' => self::str($documento['Numero'] ?? null),
            'unidade' => $unidade,
            'assinaturas' => [],
            'nivelAcesso' => (int) ($documento['NivelAcesso'] ?? $p['nivelAcesso']),
        ];
        $this->registrarAndamento($p, self::TAREFA_INCLUSAO_DOCUMENTO, "Gerado documento {$formatado}", $unidade);

        return Respostas::documentoIncluido($formatado, $this->documentos[$formatado]['id']);
    }

    /**
     * @param  array<string, mixed>  $p  Processo (por referência)
     * @param  array<string, string|null>  $atributos
     * @return array<string, mixed>
     */
    private function registrarAndamento(array &$p, int|string $tarefa, ?string $descricao, string $unidade, array $atributos = []): array
    {
        $id = (string) (1000 + ++$this->seqAndamento);
        $andamento = Respostas::andamento($id, $tarefa, $descricao, new DateTimeImmutable, $unidade, $atributos);
        $andamento['Unidade'] = $this->unidade($unidade);
        $p['andamentos'][] = $andamento;

        return $andamento;
    }

    /** @return array<string, mixed> */
    private function unidade(string $id): array
    {
        return Respostas::unidade($id, $this->unidades[$id] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function &processoRef(string $protocolo, string $operation): array
    {
        $chave = $this->chaveProcesso($protocolo);
        if ($chave === null) {
            throw new FaultException("Processo {$protocolo} não encontrado.", $operation);
        }

        return $this->processos[$chave];
    }

    private function chaveProcesso(string $protocolo): ?string
    {
        if (isset($this->processos[$protocolo])) {
            return $protocolo;
        }
        foreach ($this->processos as $chave => $p) {
            if ($p['id'] === $protocolo) {
                return $chave;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function &documentoRef(string $documento, string $operation): array
    {
        foreach ($this->documentos as $chave => $d) {
            if ($chave === $documento || $d['id'] === $documento) {
                return $this->documentos[$chave];
            }
        }

        throw new FaultException("Documento {$documento} não encontrado.", $operation);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function arr(mixed $value): array
    {
        if ($value instanceof \stdClass) {
            return get_object_vars($value);
        }

        return is_array($value) ? $value : [];
    }

    private static function str(mixed $value): ?string
    {
        return $value === null || $value === '' || ! is_scalar($value) ? null : (string) $value;
    }
}

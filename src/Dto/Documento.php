<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use DateTimeInterface;
use MonteiroTi\Sei\Core\Exceptions\InvalidArgumentException;
use MonteiroTi\Sei\Core\Support\Charset;
use MonteiroTi\Sei\Core\Support\ToSoap;
use MonteiroTi\Sei\Core\Support\Values;
use MonteiroTi\Sei\Enum\NivelAcesso;
use MonteiroTi\Sei\Enum\TipoDocumento;

/**
 * Estrutura Documento, usada em gerarProcedimento e incluirDocumento.
 *
 * Prefira as fábricas Documento::gerado() e Documento::externo(). O conteúdo é
 * informado sem Base64: a biblioteca codifica na serialização.
 */
final readonly class Documento implements ToSoap
{
    /**
     * @param  string|null  $conteudo  Gerado: HTML da seção principal. Externo: bytes do arquivo.
     * @param  list<ContatoRef>  $interessados
     * @param  list<ContatoRef>  $destinatarios
     * @param  list<SecaoDocumento>  $secoes  Apenas para documentos gerados
     * @param  list<Campo>  $campos  Campos de formulário
     * @param  string|null  $idArquivo  Arquivo enviado antes por Documentos::enviarArquivo() (externos)
     */
    public function __construct(
        public TipoDocumento $tipo,
        public string $idSerie,
        public ?string $idProcedimento = null,
        public ?string $protocoloProcedimento = null,
        public ?string $numero = null,
        public ?string $nomeArvore = null,
        public ?string $dinValor = null,
        public ?DateTimeInterface $data = null,
        public ?string $descricao = null,
        public ?string $idTipoConferencia = null,
        public ?bool $arquivamento = null,
        public ?ContatoRef $remetente = null,
        public array $interessados = [],
        public array $destinatarios = [],
        public ?string $observacao = null,
        public ?string $nomeArquivo = null,
        public ?NivelAcesso $nivelAcesso = null,
        public ?string $idHipoteseLegal = null,
        public ?string $conteudo = null,
        public array $secoes = [],
        public ?string $idArquivo = null,
        public array $campos = [],
        public ?bool $bloqueado = null,
        public ?string $idItemEtapa = null,
    ) {}

    /**
     * Documento gerado no editor do SEI (tipo G).
     *
     * @param  string|null  $conteudo  HTML da seção principal (sem Base64)
     * @param  list<ContatoRef>  $interessados
     * @param  list<ContatoRef>  $destinatarios
     * @param  list<SecaoDocumento>  $secoes
     * @param  list<Campo>  $campos
     * @param  string|null  $numero  Somente para tipos com numeração informada
     */
    public static function gerado(
        string $idSerie,
        ?string $conteudo = null,
        ?string $descricao = null,
        ?string $protocoloProcedimento = null,
        ?string $idProcedimento = null,
        ?string $numero = null,
        ?string $nomeArvore = null,
        array $interessados = [],
        array $destinatarios = [],
        ?string $observacao = null,
        ?NivelAcesso $nivelAcesso = null,
        ?string $idHipoteseLegal = null,
        array $secoes = [],
        array $campos = [],
        ?bool $bloqueado = null,
        ?string $dinValor = null,
        ?string $idItemEtapa = null,
    ): self {
        return new self(
            tipo: TipoDocumento::Gerado,
            idSerie: $idSerie,
            idProcedimento: $idProcedimento,
            protocoloProcedimento: $protocoloProcedimento,
            numero: $numero,
            nomeArvore: $nomeArvore,
            dinValor: $dinValor,
            descricao: $descricao,
            interessados: $interessados,
            destinatarios: $destinatarios,
            observacao: $observacao,
            nivelAcesso: $nivelAcesso,
            idHipoteseLegal: $idHipoteseLegal,
            conteudo: $conteudo,
            secoes: $secoes,
            campos: $campos,
            bloqueado: $bloqueado,
            idItemEtapa: $idItemEtapa,
        );
    }

    /**
     * Documento externo/recebido (tipo R). Informe $conteudo (bytes do arquivo) ou $idArquivo.
     *
     * @param  list<ContatoRef>  $interessados
     * @param  list<ContatoRef>  $destinatarios
     */
    public static function externo(
        string $idSerie,
        DateTimeInterface $data,
        string $nomeArquivo,
        ContatoRef $remetente,
        ?string $conteudo = null,
        ?string $idArquivo = null,
        ?string $protocoloProcedimento = null,
        ?string $idProcedimento = null,
        ?string $numero = null,
        ?string $nomeArvore = null,
        ?string $descricao = null,
        array $interessados = [],
        array $destinatarios = [],
        ?string $observacao = null,
        ?NivelAcesso $nivelAcesso = null,
        ?string $idHipoteseLegal = null,
        ?string $idTipoConferencia = null,
        ?bool $arquivamento = null,
        ?bool $bloqueado = null,
        ?string $dinValor = null,
        ?string $idItemEtapa = null,
    ): self {
        $documento = new self(
            tipo: TipoDocumento::Externo,
            idSerie: $idSerie,
            idProcedimento: $idProcedimento,
            protocoloProcedimento: $protocoloProcedimento,
            numero: $numero,
            nomeArvore: $nomeArvore,
            dinValor: $dinValor,
            data: $data,
            descricao: $descricao,
            idTipoConferencia: $idTipoConferencia,
            arquivamento: $arquivamento,
            remetente: $remetente,
            interessados: $interessados,
            destinatarios: $destinatarios,
            observacao: $observacao,
            nomeArquivo: $nomeArquivo,
            nivelAcesso: $nivelAcesso,
            idHipoteseLegal: $idHipoteseLegal,
            conteudo: $conteudo,
            idArquivo: $idArquivo,
            bloqueado: $bloqueado,
            idItemEtapa: $idItemEtapa,
        );
        $documento->validate();

        return $documento;
    }

    /** Cópia apontando para um processo existente (use em incluirDocumento). */
    public function noProcesso(string $protocoloProcedimento): self
    {
        return $this->with(['protocoloProcedimento' => $protocoloProcedimento, 'idProcedimento' => null]);
    }

    /** Cópia apontando para um processo existente pelo ID interno. */
    public function noProcessoId(string $idProcedimento): self
    {
        return $this->with(['idProcedimento' => $idProcedimento, 'protocoloProcedimento' => null]);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        return new self(...array_replace(get_object_vars($this), $changes));
    }

    public function validate(): void
    {
        if ($this->tipo === TipoDocumento::Externo) {
            $faltando = array_keys(array_filter([
                'data' => $this->data === null,
                'nomeArquivo' => $this->nomeArquivo === null || $this->nomeArquivo === '',
                'remetente' => $this->remetente === null,
            ]));
            if ($faltando !== []) {
                throw new InvalidArgumentException('Documento externo exige: '.implode(', ', $faltando).'.');
            }
            if (($this->conteudo === null) === ($this->idArquivo === null)) {
                throw new InvalidArgumentException('Documento externo exige exatamente um entre conteudo e idArquivo.');
            }
            if ($this->secoes !== []) {
                throw new InvalidArgumentException('Seções só podem ser usadas em documentos gerados.');
            }
        } elseif ($this->conteudo !== null && $this->secoes !== []) {
            throw new InvalidArgumentException('Informe o conteúdo principal ou as seções do documento, não ambos.');
        }
    }

    /**
     * @param  string  $charset  Charset do HTML de documentos gerados antes do Base64
     */
    public function toSoap(string $charset = 'UTF-8'): array
    {
        $this->validate();
        $gerado = $this->tipo === TipoDocumento::Gerado;

        $conteudo = null;
        if ($this->conteudo !== null) {
            $conteudo = base64_encode($gerado ? Charset::fromUtf8($this->conteudo, $charset) : $this->conteudo);
        }

        return [
            'Tipo' => $this->tipo->value,
            'IdProcedimento' => $this->idProcedimento,
            'ProtocoloProcedimento' => $this->protocoloProcedimento,
            'IdSerie' => $this->idSerie,
            'Numero' => $this->numero,
            'NomeArvore' => $this->nomeArvore,
            'DinValor' => $this->dinValor,
            'Data' => Values::date($this->data),
            'Descricao' => $this->descricao,
            'IdTipoConferencia' => $this->idTipoConferencia,
            'SinArquivamento' => Values::sn($this->arquivamento),
            'Remetente' => $this->remetente?->toSoap(),
            'Interessados' => Values::list($this->interessados),
            'Destinatarios' => Values::list($this->destinatarios),
            'Observacao' => $this->observacao,
            'NomeArquivo' => $this->nomeArquivo,
            'NivelAcesso' => $this->nivelAcesso?->value,
            'IdHipoteseLegal' => $this->idHipoteseLegal,
            'Conteudo' => $conteudo,
            'ConteudoSecoes' => array_map(fn (SecaoDocumento $s) => $s->toSoap($charset), $this->secoes),
            'ConteudoMTOM' => null,
            'IdArquivo' => $this->idArquivo,
            'Campos' => Values::list($this->campos),
            'SinBloqueado' => Values::sn($this->bloqueado),
            'IdItemEtapa' => $this->idItemEtapa,
        ];
    }
}

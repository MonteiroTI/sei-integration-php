<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Testing;

use DateTimeImmutable;
use DateTimeInterface;
use MonteiroTi\Sei\Core\Support\Values;
use MonteiroTi\Sei\Enum\NivelAcesso;

/**
 * Respostas prontas no formato que o \SoapClient devolveria, para usar com o fake:
 *
 *     Sei::fake()->respond('gerarProcedimento', Respostas::processoGerado('12.1.000000077-4'));
 *
 * Os nomes dos campos seguem o manual do WebService. IDs omitidos são derivados do
 * protocolo (dígitos do número formatado).
 */
final class Respostas
{
    /**
     * Retorno de gerarProcedimento.
     *
     * @param  list<array<string, mixed>>  $documentos  Itens de documentoIncluido(), na ordem enviada
     * @return array<string, mixed>
     */
    public static function processoGerado(
        string $protocolo = '12.1.000000001-1',
        ?string $idProcedimento = null,
        ?string $linkAcesso = null,
        array $documentos = [],
    ): array {
        return [
            'IdProcedimento' => $idProcedimento ?? self::digitos($protocolo),
            'ProcedimentoFormatado' => $protocolo,
            'LinkAcesso' => $linkAcesso ?? self::link('procedimento', $protocolo),
            'RetornoInclusaoDocumentos' => $documentos,
        ];
    }

    /**
     * Retorno de incluirDocumento (e item de processoGerado()).
     *
     * @return array<string, mixed>
     */
    public static function documentoIncluido(string $documentoFormatado = '0000001', ?string $idDocumento = null, ?string $linkAcesso = null): array
    {
        return [
            'IdDocumento' => $idDocumento ?? self::digitos($documentoFormatado),
            'DocumentoFormatado' => $documentoFormatado,
            'LinkAcesso' => $linkAcesso ?? self::link('documento', $documentoFormatado),
        ];
    }

    /**
     * Retorno de consultarProcedimento.
     *
     * @param  list<string|array<string, mixed>>  $unidadesAbertas  IDs de unidade ou itens de unidade()
     * @param  array<string, mixed>|null  $ultimoAndamento  Item de andamento()
     * @param  array<string, mixed>|null  $andamentoConclusao  Item de andamento()
     * @param  array<string, mixed>|null  $andamentoGeracao  Item de andamento()
     * @return array<string, mixed>
     */
    public static function processo(
        string $protocolo = '12.1.000000001-1',
        ?string $idProcedimento = null,
        array $unidadesAbertas = [],
        ?array $ultimoAndamento = null,
        ?array $andamentoConclusao = null,
        ?array $andamentoGeracao = null,
        ?string $especificacao = null,
        ?string $idTipoProcedimento = null,
        NivelAcesso $nivelAcesso = NivelAcesso::Publico,
        ?DateTimeInterface $dataAutuacao = null,
        ?string $linkAcesso = null,
    ): array {
        return [
            'IdProcedimento' => $idProcedimento ?? self::digitos($protocolo),
            'ProcedimentoFormatado' => $protocolo,
            'Especificacao' => $especificacao,
            'DataAutuacao' => Values::date($dataAutuacao ?? new DateTimeImmutable),
            'NivelAcessoLocal' => (string) $nivelAcesso->value,
            'NivelAcessoGlobal' => (string) $nivelAcesso->value,
            'LinkAcesso' => $linkAcesso ?? self::link('procedimento', $protocolo),
            'TipoProcedimento' => $idTipoProcedimento === null ? null : ['IdTipoProcedimento' => $idTipoProcedimento, 'Nome' => null],
            'AndamentoGeracao' => $andamentoGeracao,
            'AndamentoConclusao' => $andamentoConclusao,
            'UltimoAndamento' => $ultimoAndamento,
            'UnidadesProcedimentoAberto' => array_map(
                fn (string|array $u) => ['Unidade' => is_array($u) ? $u : self::unidade($u), 'UsuarioAtribuicao' => null],
                $unidadesAbertas,
            ),
            'Assuntos' => [],
            'Interessados' => [],
            'Observacoes' => [],
            'ProcedimentosRelacionados' => [],
            'ProcedimentosAnexados' => [],
        ];
    }

    /**
     * Retorno de consultarDocumento.
     *
     * @param  list<array<string, mixed>>  $assinaturas  Itens de assinatura()
     * @param  array<string, mixed>|null  $publicacao  Estrutura Publicacao do manual
     * @return array<string, mixed>
     */
    public static function documento(
        string $documentoFormatado = '0000001',
        string $protocoloProcesso = '12.1.000000001-1',
        ?string $idDocumento = null,
        ?string $idSerie = null,
        ?string $nomeSerie = null,
        ?string $numero = null,
        array $assinaturas = [],
        ?array $publicacao = null,
        ?string $idUnidadeElaboradora = null,
        NivelAcesso $nivelAcesso = NivelAcesso::Publico,
        ?string $linkAcesso = null,
    ): array {
        return [
            'IdProcedimento' => self::digitos($protocoloProcesso),
            'ProcedimentoFormatado' => $protocoloProcesso,
            'IdDocumento' => $idDocumento ?? self::digitos($documentoFormatado),
            'DocumentoFormatado' => $documentoFormatado,
            'NivelAcessoLocal' => (string) $nivelAcesso->value,
            'NivelAcessoGlobal' => (string) $nivelAcesso->value,
            'LinkAcesso' => $linkAcesso ?? self::link('documento', $documentoFormatado),
            'Serie' => $idSerie === null ? null : ['IdSerie' => $idSerie, 'Nome' => $nomeSerie],
            'Numero' => $numero,
            'Data' => Values::date(new DateTimeImmutable),
            'UnidadeElaboradora' => $idUnidadeElaboradora === null ? null : self::unidade($idUnidadeElaboradora),
            'AndamentoGeracao' => null,
            'Assinaturas' => $assinaturas,
            'Publicacao' => $publicacao,
            'Campos' => [],
            'Blocos' => [],
        ];
    }

    /**
     * Estrutura Andamento (UltimoAndamento, itens de listarAndamentos, retorno de lancarAndamento).
     *
     * @param  array<string, string|null>  $atributos  Nome => valor
     * @return array<string, mixed>
     */
    public static function andamento(
        string $idAndamento,
        int|string $idTarefa = 65,
        ?string $descricao = null,
        ?DateTimeInterface $dataHora = null,
        ?string $idUnidade = null,
        array $atributos = [],
    ): array {
        $lista = [];
        foreach ($atributos as $nome => $valor) {
            $lista[] = ['Nome' => (string) $nome, 'Valor' => $valor, 'IdOrigem' => null];
        }

        return [
            'IdAndamento' => $idAndamento,
            'IdTarefa' => (string) $idTarefa,
            'IdTarefaModulo' => null,
            'Descricao' => $descricao,
            'DataHora' => Values::dateTime($dataHora ?? new DateTimeImmutable),
            'Unidade' => $idUnidade === null ? null : self::unidade($idUnidade),
            'Usuario' => null,
            'Atributos' => $lista,
        ];
    }

    /**
     * Estrutura Assinatura (itens de documento(assinaturas: ...)).
     *
     * @return array<string, mixed>
     */
    public static function assinatura(string $nome = 'Fulano de Tal', ?string $sigla = null, ?string $cargoFuncao = null, ?DateTimeInterface $dataHora = null): array
    {
        return [
            'Nome' => $nome,
            'CargoFuncao' => $cargoFuncao,
            'DataHora' => Values::dateTime($dataHora ?? new DateTimeImmutable),
            'IdUsuario' => null,
            'IdOrigem' => null,
            'IdOrgao' => null,
            'Sigla' => $sigla ?? strtolower(strtok($nome, ' ') ?: $nome),
        ];
    }

    /** @return array<string, mixed> */
    public static function unidade(string $idUnidade, ?string $sigla = null, ?string $descricao = null): array
    {
        return [
            'IdUnidade' => $idUnidade,
            'Sigla' => $sigla ?? 'U'.$idUnidade,
            'Descricao' => $descricao ?? $sigla ?? 'Unidade '.$idUnidade,
            'SinProtocolo' => 'N',
            'SinArquivamento' => 'N',
            'SinOuvidoria' => 'N',
        ];
    }

    /**
     * Retorno de listarUnidades.
     *
     * @param  array<string, string>  $unidades  ID => sigla
     * @return list<array<string, mixed>>
     */
    public static function unidades(array $unidades): array
    {
        $lista = [];
        foreach ($unidades as $id => $sigla) {
            $lista[] = self::unidade((string) $id, $sigla);
        }

        return $lista;
    }

    /**
     * Retorno de listarSeries.
     *
     * @param  array<string, string>  $series  ID => nome
     * @return list<array<string, mixed>>
     */
    public static function series(array $series): array
    {
        $lista = [];
        foreach ($series as $id => $nome) {
            $lista[] = ['IdSerie' => (string) $id, 'Nome' => $nome, 'Aplicabilidade' => null];
        }

        return $lista;
    }

    private static function digitos(string $formatado): string
    {
        return (string) preg_replace('/\D/', '', $formatado);
    }

    private static function link(string $tipo, string $protocolo): string
    {
        return 'https://sei.simulado/sei/controlador.php?acao='.$tipo.'_trabalhar&protocolo='.rawurlencode($protocolo);
    }
}

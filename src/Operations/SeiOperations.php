<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Operations;

/**
 * Catálogo das operações do WebService do SEI 5.0 e da ordem posicional dos parâmetros.
 *
 * Fonte: manual "SEI WebServices v5.0", cap. 3. A ordem deve coincidir com a WSDL
 * (controlador_ws.php?servico=sei); o teste de contrato confere isso quando a WSDL
 * real está disponível em tests/Fixtures.
 */
final class SeiOperations
{
    private const AUTH = ['SiglaSistema', 'IdentificacaoServico'];

    private const STD = ['SiglaSistema', 'IdentificacaoServico', 'IdUnidade'];

    /** Operações em que IdUnidade é opcional (filtro) e não recebe a unidade padrão. */
    public const OPTIONAL_UNIDADE = [
        'listarSeries',
        'listarTiposConferencia',
        'listarTiposPrioridade',
        'listarTiposProcedimento',
    ];

    /** @var array<string, list<string>> */
    private const EXTRA = [
        'adicionarArquivo' => ['Nome', 'Tamanho', 'Hash', 'Conteudo'],
        'adicionarConteudoArquivo' => ['IdArquivo', 'Conteudo'],
        'agendarPublicacao' => ['IdDocumento', 'ProtocoloDocumento', 'StaMotivo', 'IdVeiculoPublicacao', 'DataDisponibilizacao', 'Resumo', 'ImprensaNacional'],
        'alterarPublicacao' => ['IdPublicacao', 'IdDocumento', 'ProtocoloDocumento', 'StaMotivo', 'IdVeiculoPublicacao', 'DataDisponibilizacao', 'Resumo', 'ImprensaNacional'],
        'atribuirProcesso' => ['ProtocoloProcedimento', 'IdUsuario', 'SinReabrir'],
        'anexarProcesso' => ['ProtocoloProcedimentoPrincipal', 'ProtocoloProcedimentoAnexado'],
        'atualizarContatos' => ['Contatos'],
        'bloquearProcesso' => ['ProtocoloProcedimento'],
        'cancelarAgendamentoPublicacao' => ['IdPublicacao', 'IdDocumento', 'ProtocoloDocumento'],
        'cancelarDisponibilizacaoBloco' => ['IdBloco'],
        'cancelarDocumento' => ['ProtocoloDocumento', 'Motivo'],
        'concluirBloco' => ['IdBloco'],
        'concluirControlePrazo' => ['ProtocolosProcedimentos'],
        'concluirProcesso' => ['ProtocoloProcedimento'],
        'consultarBloco' => ['IdBloco', 'SinRetornarProtocolos'],
        'consultarDocumento' => ['ProtocoloDocumento', 'SinRetornarAndamentoGeracao', 'SinRetornarAssinaturas', 'SinRetornarPublicacao', 'SinRetornarCampos', 'SinRetornarBlocos'],
        'consultarProcedimento' => ['ProtocoloProcedimento', 'SinRetornarAssuntos', 'SinRetornarInteressados', 'SinRetornarObservacoes', 'SinRetornarAndamentoGeracao', 'SinRetornarAndamentoConclusao', 'SinRetornarUltimoAndamento', 'SinRetornarUnidadesProcedimentoAberto', 'SinRetornarProcedimentosRelacionados', 'SinRetornarProcedimentosAnexados'],
        'consultarProcedimentoIndividual' => ['IdOrgaoProcedimento', 'IdTipoProcedimento', 'IdOrgaoUsuario', 'SiglaUsuario'],
        'consultarPublicacao' => ['IdPublicacao', 'IdDocumento', 'ProtocoloDocumento', 'SinRetornarAndamento', 'SinRetornarAssinaturas'],
        'definirControlePrazo' => ['Definicoes'],
        'definirMarcador' => ['Definicoes'],
        'desanexarProcesso' => ['ProtocoloProcedimentoPrincipal', 'ProtocoloProcedimentoAnexado', 'Motivo'],
        'desbloquearProcesso' => ['ProtocoloProcedimento'],
        'devolverBloco' => ['IdBloco'],
        'disponibilizarBloco' => ['IdBloco'],
        'enviarEmail' => ['ProtocoloProcedimento', 'De', 'Para', 'CCO', 'Assunto', 'Mensagem', 'IdDocumentos', 'NivelAcesso', 'IdHipoteseLegal'],
        'enviarProcesso' => ['ProtocoloProcedimento', 'UnidadesDestino', 'SinManterAbertoUnidade', 'SinRemoverAnotacao', 'SinEnviarEmailNotificacao', 'DataRetornoProgramado', 'DiasRetornoProgramado', 'SinDiasUteisRetornoProgramado', 'SinReabrir'],
        'excluirBloco' => ['IdBloco'],
        'excluirDocumento' => ['ProtocoloDocumento'],
        'excluirProcesso' => ['ProtocoloProcedimento'],
        'gerarBloco' => ['Tipo', 'Descricao', 'UnidadesDisponibilizacao', 'Documentos', 'SinDisponibilizar'],
        'gerarProcedimento' => ['Procedimento', 'Documentos', 'ProcedimentosRelacionados', 'UnidadesEnvio', 'SinManterAbertoUnidade', 'SinEnviarEmailNotificacao', 'DataRetornoProgramado', 'DiasRetornoProgramado', 'SinDiasUteisRetornoProgramado', 'IdMarcador', 'TextoMarcador', 'DataControlePrazo', 'DiasControlePrazo', 'SinDiasUteisControlePrazo'],
        'incluirDocumento' => ['Documento'],
        'incluirDocumentoBloco' => ['IdBloco', 'ProtocoloDocumento', 'Anotacao'],
        'incluirProcessoBloco' => ['IdBloco', 'ProtocoloProcedimento', 'Anotacao'],
        'lancarAndamento' => ['ProtocoloProcedimento', 'IdTarefa', 'IdTarefaModulo', 'Atributos'],
        'listarAndamentos' => ['ProtocoloProcedimento', 'SinRetornarAtributos', 'Andamentos', 'Tarefas', 'TarefasModulos'],
        'listarAndamentosMarcadores' => ['ProtocoloProcedimento', 'Marcadores'],
        'listarCargos' => ['IdCargo'],
        'listarCidades' => ['IdPais', 'IdEstado'],
        'listarContatos' => ['IdTipoContato', 'PaginaRegistros', 'PaginaAtual', 'Sigla', 'Nome', 'Cpf', 'Cnpj', 'Matricula', 'IdContatos'],
        'listarEstados' => ['IdPais'],
        'listarExtensoesPermitidas' => ['IdArquivoExtensao'],
        'listarFeriados' => ['IdOrgao', 'DataInicial', 'DataFinal'],
        'listarHipotesesLegais' => ['NivelAcesso'],
        'listarMarcadoresUnidade' => [],
        'listarPaises' => [],
        'listarSeries' => ['IdTipoProcedimento'],
        'listarTiposConferencia' => [],
        'listarTiposPrioridade' => [],
        'listarTiposProcedimento' => ['IdSerie', 'SinIndividual'],
        'listarUsuarios' => ['IdUsuario'],
        'reabrirBloco' => ['IdBloco'],
        'reabrirProcesso' => ['ProtocoloProcedimento'],
        'registrarAnotacao' => ['Anotacoes'],
        'relacionarProcesso' => ['ProtocoloProcedimento1', 'ProtocoloProcedimento2'],
        'removerControlePrazo' => ['ProtocolosProcedimentos'],
        'removerRelacionamentoProcesso' => ['ProtocoloProcedimento1', 'ProtocoloProcedimento2'],
        'removerSobrestamentoProcesso' => ['ProtocoloProcedimento'],
        'retirarDocumentoBloco' => ['IdBloco', 'ProtocoloDocumento'],
        'retirarProcessoBloco' => ['IdBloco', 'ProtocoloProcedimento'],
        'sobrestarProcesso' => ['ProtocoloProcedimento', 'ProtocoloProcedimentoVinculado', 'Motivo'],
    ];

    /** Operações sem IdUnidade (apenas SiglaSistema e IdentificacaoServico antes dos demais). */
    private const WITHOUT_UNIDADE = [
        'confirmarDisponibilizacaoPublicacao' => ['IdVeiculoPublicacao', 'DataDisponibilizacao', 'DataPublicacao', 'Numero', 'IdDocumentos'],
        'listarTiposProcedimentoOuvidoria' => [],
        'listarUnidades' => ['IdTipoProcedimento', 'IdSerie'],
        'registrarOuvidoria' => ['IdOrgao', 'Nome', 'NomeSocial', 'Email', 'Cpf', 'Rg', 'OrgaoExpedidor', 'Telefone', 'Estado', 'Cidade', 'IdTipoProcedimento', 'Processos', 'SinRetorno', 'SinAnonimo', 'SinSigilo', 'Mensagem', 'Anexos', 'AtributosAdicionais'],
    ];

    /**
     * Parâmetros da operação, na ordem posicional da WSDL.
     *
     * @return list<string>
     */
    public static function parameters(string $operation): array
    {
        if (isset(self::EXTRA[$operation])) {
            return [...self::STD, ...self::EXTRA[$operation]];
        }
        if (isset(self::WITHOUT_UNIDADE[$operation])) {
            return [...self::AUTH, ...self::WITHOUT_UNIDADE[$operation]];
        }

        throw new \InvalidArgumentException(sprintf('Operação SEI desconhecida: "%s".', $operation));
    }

    public static function exists(string $operation): bool
    {
        return isset(self::EXTRA[$operation]) || isset(self::WITHOUT_UNIDADE[$operation]);
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $all = [...array_keys(self::EXTRA), ...array_keys(self::WITHOUT_UNIDADE)];
        sort($all);

        return $all;
    }

    public static function requiresUnidade(string $operation): bool
    {
        return isset(self::EXTRA[$operation]) && ! in_array($operation, self::OPTIONAL_UNIDADE, true);
    }
}

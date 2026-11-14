<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Api;

use MonteiroTi\Sei\Core\Support\SoapData;
use MonteiroTi\Sei\Core\Support\Values;
use MonteiroTi\Sei\Dto\Anotacao;
use MonteiroTi\Sei\Dto\DefinicaoControlePrazo;
use MonteiroTi\Sei\Dto\DefinicaoMarcador;
use MonteiroTi\Sei\Dto\Documento;
use MonteiroTi\Sei\Dto\Procedimento;
use MonteiroTi\Sei\Dto\ProcedimentoResumido;
use MonteiroTi\Sei\Dto\RetornoConsultaProcedimento;
use MonteiroTi\Sei\Dto\RetornoEnvioEmail;
use MonteiroTi\Sei\Dto\RetornoGeracaoProcedimento;
use MonteiroTi\Sei\Options\ConsultaProcedimentoOptions;
use MonteiroTi\Sei\Options\Email;
use MonteiroTi\Sei\Options\EnviarProcessoOptions;
use MonteiroTi\Sei\Options\GerarProcedimentoOptions;

/** Operações sobre processos (procedimentos). */
final class Processos extends Api
{
    /**
     * gerarProcedimento: gera um processo, opcionalmente com documentos.
     *
     * @param  list<Documento>  $documentos  Limitado por SEI_WS_NUM_MAX_DOCS
     */
    public function gerar(Procedimento $procedimento, array $documentos = [], ?GerarProcedimentoOptions $options = null): RetornoGeracaoProcedimento
    {
        $o = $options ?? new GerarProcedimentoOptions;
        $charset = $this->client->config()->conteudoCharset;

        $result = $this->call('gerarProcedimento', [
            'Procedimento' => $procedimento->toSoap(),
            'Documentos' => array_map(fn (Documento $d) => $d->toSoap($charset), $documentos),
            'ProcedimentosRelacionados' => Values::ids($o->procedimentosRelacionados),
            'UnidadesEnvio' => Values::ids($o->unidadesEnvio),
            'SinManterAbertoUnidade' => Values::sn($o->manterAbertoUnidade),
            'SinEnviarEmailNotificacao' => Values::sn($o->enviarEmailNotificacao),
            'DataRetornoProgramado' => Values::date($o->dataRetornoProgramado),
            'DiasRetornoProgramado' => $o->diasRetornoProgramado,
            'SinDiasUteisRetornoProgramado' => Values::sn($o->diasUteisRetornoProgramado),
            'IdMarcador' => $o->idMarcador,
            'TextoMarcador' => $o->textoMarcador,
            'DataControlePrazo' => Values::date($o->dataControlePrazo),
            'DiasControlePrazo' => $o->diasControlePrazo,
            'SinDiasUteisControlePrazo' => Values::sn($o->diasUteisControlePrazo),
        ]);

        return RetornoGeracaoProcedimento::fromSoap(SoapData::from($result));
    }

    /** consultarProcedimento. Processos sigilosos não são retornados. */
    public function consultar(string $protocoloProcedimento, ?ConsultaProcedimentoOptions $options = null): RetornoConsultaProcedimento
    {
        $o = $options ?? new ConsultaProcedimentoOptions;

        $result = $this->call('consultarProcedimento', [
            'ProtocoloProcedimento' => $protocoloProcedimento,
            'SinRetornarAssuntos' => Values::sn($o->assuntos),
            'SinRetornarInteressados' => Values::sn($o->interessados),
            'SinRetornarObservacoes' => Values::sn($o->observacoes),
            'SinRetornarAndamentoGeracao' => Values::sn($o->andamentoGeracao),
            'SinRetornarAndamentoConclusao' => Values::sn($o->andamentoConclusao),
            'SinRetornarUltimoAndamento' => Values::sn($o->ultimoAndamento),
            'SinRetornarUnidadesProcedimentoAberto' => Values::sn($o->unidadesProcedimentoAberto),
            'SinRetornarProcedimentosRelacionados' => Values::sn($o->procedimentosRelacionados),
            'SinRetornarProcedimentosAnexados' => Values::sn($o->procedimentosAnexados),
        ]);

        return RetornoConsultaProcedimento::fromSoap(SoapData::from($result));
    }

    /**
     * consultarProcedimentoIndividual: processo "único no órgão por usuário interessado".
     * Devolve o mais recente, ou null se não houver.
     */
    public function consultarIndividual(string $idOrgaoProcedimento, string $idTipoProcedimento, string $idOrgaoUsuario, string $siglaUsuario): ?ProcedimentoResumido
    {
        $result = $this->call('consultarProcedimentoIndividual', [
            'IdOrgaoProcedimento' => $idOrgaoProcedimento,
            'IdTipoProcedimento' => $idTipoProcedimento,
            'IdOrgaoUsuario' => $idOrgaoUsuario,
            'SiglaUsuario' => $siglaUsuario,
        ]);

        return $result === null || $result === '' ? null : ProcedimentoResumido::fromSoap(SoapData::from($result));
    }

    /**
     * enviarProcesso: tramita o processo para outras unidades.
     *
     * @param  list<string>  $unidadesDestino  IDs das unidades de destino
     */
    public function enviar(string $protocoloProcedimento, array $unidadesDestino, ?EnviarProcessoOptions $options = null): void
    {
        $o = $options ?? new EnviarProcessoOptions;

        $this->call('enviarProcesso', [
            'ProtocoloProcedimento' => $protocoloProcedimento,
            'UnidadesDestino' => Values::ids($unidadesDestino),
            'SinManterAbertoUnidade' => Values::sn($o->manterAbertoUnidade),
            'SinRemoverAnotacao' => Values::sn($o->removerAnotacao),
            'SinEnviarEmailNotificacao' => Values::sn($o->enviarEmailNotificacao),
            'DataRetornoProgramado' => Values::date($o->dataRetornoProgramado),
            'DiasRetornoProgramado' => $o->diasRetornoProgramado,
            'SinDiasUteisRetornoProgramado' => Values::sn($o->diasUteisRetornoProgramado),
            'SinReabrir' => Values::sn($o->reabrir),
        ]);
    }

    public function concluir(string $protocoloProcedimento): void
    {
        $this->call('concluirProcesso', ['ProtocoloProcedimento' => $protocoloProcedimento]);
    }

    public function reabrir(string $protocoloProcedimento): void
    {
        $this->call('reabrirProcesso', ['ProtocoloProcedimento' => $protocoloProcedimento]);
    }

    /** Bloqueia alterações no processo. Não é possível bloquear processos sigilosos. */
    public function bloquear(string $protocoloProcedimento): void
    {
        $this->call('bloquearProcesso', ['ProtocoloProcedimento' => $protocoloProcedimento]);
    }

    public function desbloquear(string $protocoloProcedimento): void
    {
        $this->call('desbloquearProcesso', ['ProtocoloProcedimento' => $protocoloProcedimento]);
    }

    public function excluir(string $protocoloProcedimento): void
    {
        $this->call('excluirProcesso', ['ProtocoloProcedimento' => $protocoloProcedimento]);
    }

    /**
     * atribuirProcesso a um usuário da unidade.
     *
     * @param  string  $idUsuario  ID do usuário no SIP
     */
    public function atribuir(string $protocoloProcedimento, string $idUsuario, ?bool $reabrir = null): void
    {
        $this->call('atribuirProcesso', [
            'ProtocoloProcedimento' => $protocoloProcedimento,
            'IdUsuario' => $idUsuario,
            'SinReabrir' => Values::sn($reabrir),
        ]);
    }

    public function anexar(string $protocoloPrincipal, string $protocoloAnexado): void
    {
        $this->call('anexarProcesso', [
            'ProtocoloProcedimentoPrincipal' => $protocoloPrincipal,
            'ProtocoloProcedimentoAnexado' => $protocoloAnexado,
        ]);
    }

    public function desanexar(string $protocoloPrincipal, string $protocoloAnexado, string $motivo): void
    {
        $this->call('desanexarProcesso', [
            'ProtocoloProcedimentoPrincipal' => $protocoloPrincipal,
            'ProtocoloProcedimentoAnexado' => $protocoloAnexado,
            'Motivo' => $motivo,
        ]);
    }

    /** Relacionamento bilateral: a unidade precisa de permissão nos dois tipos de processo. */
    public function relacionar(string $protocolo1, string $protocolo2): void
    {
        $this->call('relacionarProcesso', ['ProtocoloProcedimento1' => $protocolo1, 'ProtocoloProcedimento2' => $protocolo2]);
    }

    public function removerRelacionamento(string $protocolo1, string $protocolo2): void
    {
        $this->call('removerRelacionamentoProcesso', ['ProtocoloProcedimento1' => $protocolo1, 'ProtocoloProcedimento2' => $protocolo2]);
    }

    public function sobrestar(string $protocoloProcedimento, string $motivo, ?string $protocoloVinculado = null): void
    {
        $this->call('sobrestarProcesso', [
            'ProtocoloProcedimento' => $protocoloProcedimento,
            'ProtocoloProcedimentoVinculado' => $protocoloVinculado,
            'Motivo' => $motivo,
        ]);
    }

    public function removerSobrestamento(string $protocoloProcedimento): void
    {
        $this->call('removerSobrestamentoProcesso', ['ProtocoloProcedimento' => $protocoloProcedimento]);
    }

    public function registrarAnotacao(Anotacao ...$anotacoes): void
    {
        $this->call('registrarAnotacao', ['Anotacoes' => Values::list($anotacoes)]);
    }

    public function definirMarcador(DefinicaoMarcador ...$definicoes): void
    {
        $this->call('definirMarcador', ['Definicoes' => Values::list($definicoes)]);
    }

    public function definirControlePrazo(DefinicaoControlePrazo ...$definicoes): void
    {
        $this->call('definirControlePrazo', ['Definicoes' => Values::list($definicoes)]);
    }

    public function concluirControlePrazo(string ...$protocolosProcedimentos): void
    {
        $this->call('concluirControlePrazo', ['ProtocolosProcedimentos' => array_values($protocolosProcedimentos)]);
    }

    public function removerControlePrazo(string ...$protocolosProcedimentos): void
    {
        $this->call('removerControlePrazo', ['ProtocolosProcedimentos' => array_values($protocolosProcedimentos)]);
    }

    /** enviarEmail: gera um documento de e-mail no processo e o envia. */
    public function enviarEmail(string $protocoloProcedimento, Email $email): RetornoEnvioEmail
    {
        $result = $this->call('enviarEmail', [
            'ProtocoloProcedimento' => $protocoloProcedimento,
            'De' => $email->de,
            'Para' => implode(';', $email->para),
            'CCO' => $email->cco === [] ? null : implode(';', $email->cco),
            'Assunto' => $email->assunto,
            'Mensagem' => $email->mensagem,
            'IdDocumentos' => Values::ids($email->idDocumentos),
            'NivelAcesso' => $email->nivelAcesso?->value,
            'IdHipoteseLegal' => $email->idHipoteseLegal,
        ]);

        return RetornoEnvioEmail::fromSoap(SoapData::from($result));
    }
}

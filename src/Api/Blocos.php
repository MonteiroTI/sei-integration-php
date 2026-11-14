<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Api;

use MonteiroTi\Sei\Core\Support\SoapData;
use MonteiroTi\Sei\Core\Support\Values;
use MonteiroTi\Sei\Dto\Bloco;
use MonteiroTi\Sei\Enum\TipoBloco;

/** Blocos de assinatura, reunião e internos. */
final class Blocos extends Api
{
    /**
     * gerarBloco.
     *
     * @param  list<string>  $unidadesDisponibilizacao  IDs das unidades
     * @param  list<string>  $documentos  Protocolos de documentos (exige a operação "Incluir Documento em Bloco")
     * @param  bool|null  $disponibilizar  Exige a operação "Disponibilizar Bloco"
     * @return string Número do bloco gerado
     */
    public function gerar(
        TipoBloco $tipo,
        string $descricao,
        array $unidadesDisponibilizacao = [],
        array $documentos = [],
        ?bool $disponibilizar = null,
    ): string {
        $result = $this->call('gerarBloco', [
            'Tipo' => $tipo->value,
            'Descricao' => $descricao,
            'UnidadesDisponibilizacao' => Values::ids($unidadesDisponibilizacao),
            'Documentos' => Values::ids($documentos),
            'SinDisponibilizar' => Values::sn($disponibilizar),
        ]);

        return (string) Values::id($result);
    }

    /**
     * consultarBloco. O bloco deve ser da unidade ou estar disponibilizado para ela.
     * Retornar os protocolos custa processamento extra no SEI.
     */
    public function consultar(string $idBloco, bool $retornarProtocolos = false): Bloco
    {
        $result = $this->call('consultarBloco', [
            'IdBloco' => $idBloco,
            'SinRetornarProtocolos' => Values::sn($retornarProtocolos),
        ]);

        return Bloco::fromSoap(SoapData::from($result));
    }

    public function concluir(string $idBloco): void
    {
        $this->call('concluirBloco', ['IdBloco' => $idBloco]);
    }

    public function reabrir(string $idBloco): void
    {
        $this->call('reabrirBloco', ['IdBloco' => $idBloco]);
    }

    public function excluir(string $idBloco): void
    {
        $this->call('excluirBloco', ['IdBloco' => $idBloco]);
    }

    public function disponibilizar(string $idBloco): void
    {
        $this->call('disponibilizarBloco', ['IdBloco' => $idBloco]);
    }

    public function cancelarDisponibilizacao(string $idBloco): void
    {
        $this->call('cancelarDisponibilizacaoBloco', ['IdBloco' => $idBloco]);
    }

    /** devolverBloco: chamado pela unidade para a qual o bloco foi disponibilizado. */
    public function devolver(string $idBloco): void
    {
        $this->call('devolverBloco', ['IdBloco' => $idBloco]);
    }

    public function incluirDocumento(string $idBloco, string $protocoloDocumento, ?string $anotacao = null): void
    {
        $this->call('incluirDocumentoBloco', [
            'IdBloco' => $idBloco,
            'ProtocoloDocumento' => $protocoloDocumento,
            'Anotacao' => $anotacao,
        ]);
    }

    public function incluirProcesso(string $idBloco, string $protocoloProcedimento, ?string $anotacao = null): void
    {
        $this->call('incluirProcessoBloco', [
            'IdBloco' => $idBloco,
            'ProtocoloProcedimento' => $protocoloProcedimento,
            'Anotacao' => $anotacao,
        ]);
    }

    public function retirarDocumento(string $idBloco, string $protocoloDocumento): void
    {
        $this->call('retirarDocumentoBloco', ['IdBloco' => $idBloco, 'ProtocoloDocumento' => $protocoloDocumento]);
    }

    public function retirarProcesso(string $idBloco, string $protocoloProcedimento): void
    {
        $this->call('retirarProcessoBloco', ['IdBloco' => $idBloco, 'ProtocoloProcedimento' => $protocoloProcedimento]);
    }
}

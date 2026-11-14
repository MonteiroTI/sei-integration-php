<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Api;

use DateTimeInterface;
use MonteiroTi\Sei\Core\Support\SoapData;
use MonteiroTi\Sei\Core\Support\Values;
use MonteiroTi\Sei\Dto\RetornoConsultaPublicacao;
use MonteiroTi\Sei\Options\DadosPublicacao;

/**
 * Publicações (Diário Oficial / veículos de publicação).
 *
 * O documento é identificado por protocoloDocumento OU idDocumento (e, quando aplicável, idPublicacao).
 */
final class Publicacoes extends Api
{
    /**
     * agendarPublicacao.
     *
     * @return string IdPublicacao
     */
    public function agendar(DadosPublicacao $dados, ?string $protocoloDocumento = null, ?string $idDocumento = null): string
    {
        $this->requireOne(['protocoloDocumento' => $protocoloDocumento, 'idDocumento' => $idDocumento]);

        $result = $this->call('agendarPublicacao', [
            'IdDocumento' => $idDocumento,
            'ProtocoloDocumento' => $protocoloDocumento,
            ...$this->dados($dados),
        ]);

        return (string) Values::id($result);
    }

    public function alterar(
        DadosPublicacao $dados,
        ?string $idPublicacao = null,
        ?string $protocoloDocumento = null,
        ?string $idDocumento = null,
    ): void {
        $this->requireOne(['idPublicacao' => $idPublicacao, 'protocoloDocumento' => $protocoloDocumento, 'idDocumento' => $idDocumento]);

        $this->call('alterarPublicacao', [
            'IdPublicacao' => $idPublicacao,
            'IdDocumento' => $idDocumento,
            'ProtocoloDocumento' => $protocoloDocumento,
            ...$this->dados($dados),
        ]);
    }

    public function cancelarAgendamento(?string $idPublicacao = null, ?string $protocoloDocumento = null, ?string $idDocumento = null): void
    {
        $this->requireOne(['idPublicacao' => $idPublicacao, 'protocoloDocumento' => $protocoloDocumento, 'idDocumento' => $idDocumento]);

        $this->call('cancelarAgendamentoPublicacao', [
            'IdPublicacao' => $idPublicacao,
            'IdDocumento' => $idDocumento,
            'ProtocoloDocumento' => $protocoloDocumento,
        ]);
    }

    /**
     * confirmarDisponibilizacaoPublicacao: registra número e data efetiva da publicação,
     * torna os documentos públicos e os disponibiliza na pesquisa de publicações.
     *
     * @param  list<string>  $idDocumentos  IDs internos dos documentos
     */
    public function confirmarDisponibilizacao(
        string $idVeiculoPublicacao,
        DateTimeInterface $dataDisponibilizacao,
        DateTimeInterface $dataPublicacao,
        string $numero,
        array $idDocumentos,
    ): void {
        $this->call('confirmarDisponibilizacaoPublicacao', [
            'IdVeiculoPublicacao' => $idVeiculoPublicacao,
            'DataDisponibilizacao' => Values::date($dataDisponibilizacao),
            'DataPublicacao' => Values::date($dataPublicacao),
            'Numero' => $numero,
            'IdDocumentos' => Values::ids($idDocumentos),
        ]);
    }

    public function consultar(
        ?string $idPublicacao = null,
        ?string $protocoloDocumento = null,
        ?string $idDocumento = null,
        bool $retornarAndamento = false,
        bool $retornarAssinaturas = false,
    ): RetornoConsultaPublicacao {
        $this->requireOne(['idPublicacao' => $idPublicacao, 'protocoloDocumento' => $protocoloDocumento, 'idDocumento' => $idDocumento], exactlyOne: false);

        $result = $this->call('consultarPublicacao', [
            'IdPublicacao' => $idPublicacao,
            'IdDocumento' => $idDocumento,
            'ProtocoloDocumento' => $protocoloDocumento,
            'SinRetornarAndamento' => Values::sn($retornarAndamento),
            'SinRetornarAssinaturas' => Values::sn($retornarAssinaturas),
        ]);

        return RetornoConsultaPublicacao::fromSoap(SoapData::from($result));
    }

    /**
     * @return array<string, mixed>
     */
    private function dados(DadosPublicacao $dados): array
    {
        return [
            'StaMotivo' => $dados->motivo->value,
            'IdVeiculoPublicacao' => $dados->idVeiculoPublicacao,
            'DataDisponibilizacao' => Values::date($dados->dataDisponibilizacao),
            'Resumo' => $dados->resumo,
            'ImprensaNacional' => $dados->imprensaNacional?->toSoap(),
        ];
    }
}

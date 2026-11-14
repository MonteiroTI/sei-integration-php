<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Api;

use MonteiroTi\Sei\Core\Support\SoapData;
use MonteiroTi\Sei\Core\Support\Values;
use MonteiroTi\Sei\Dto\ProcedimentoResumido;
use MonteiroTi\Sei\Dto\TipoProcedimento;
use MonteiroTi\Sei\Options\ManifestacaoOuvidoria;

/** Manifestações de ouvidoria. */
final class Ouvidoria extends Api
{
    /** registrarOuvidoria: gera o processo de ouvidoria no órgão informado. */
    public function registrar(ManifestacaoOuvidoria $m): ProcedimentoResumido
    {
        $result = $this->call('registrarOuvidoria', [
            'IdOrgao' => $m->idOrgao,
            'Nome' => $m->nome,
            'NomeSocial' => $m->nomeSocial,
            'Email' => $m->email,
            'Cpf' => $m->cpf,
            'Rg' => $m->rg,
            'OrgaoExpedidor' => $m->orgaoExpedidor,
            'Telefone' => $m->telefone,
            'Estado' => $m->estado,
            'Cidade' => $m->cidade,
            'IdTipoProcedimento' => $m->idTipoProcedimento,
            'Processos' => $m->processos,
            'SinRetorno' => Values::sn($m->retorno),
            'SinAnonimo' => Values::sn($m->anonimo),
            'SinSigilo' => Values::sn($m->sigilo),
            'Mensagem' => $m->mensagem,
            'Anexos' => Values::list($m->anexos),
            'AtributosAdicionais' => $m->atributosAdicionais === [] ? null : Values::list($m->atributosAdicionais),
        ]);

        return ProcedimentoResumido::fromSoap(SoapData::from($result));
    }

    /**
     * listarTiposProcedimentoOuvidoria.
     *
     * @return list<TipoProcedimento>
     */
    public function tiposProcedimento(): array
    {
        return SoapData::hydrateList($this->call('listarTiposProcedimentoOuvidoria'), TipoProcedimento::class);
    }
}

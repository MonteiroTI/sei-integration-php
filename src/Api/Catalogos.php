<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Api;

use DateTimeInterface;
use MonteiroTi\Sei\Core\Support\CatalogCache;
use MonteiroTi\Sei\Core\Support\SoapData;
use MonteiroTi\Sei\Core\Support\Values;
use MonteiroTi\Sei\Dto\ArquivoExtensao;
use MonteiroTi\Sei\Dto\Cargo;
use MonteiroTi\Sei\Dto\Cidade;
use MonteiroTi\Sei\Dto\Estado;
use MonteiroTi\Sei\Dto\Feriado;
use MonteiroTi\Sei\Dto\HipoteseLegal;
use MonteiroTi\Sei\Dto\Marcador;
use MonteiroTi\Sei\Dto\Pais;
use MonteiroTi\Sei\Dto\Serie;
use MonteiroTi\Sei\Dto\TipoConferencia;
use MonteiroTi\Sei\Dto\TipoPrioridade;
use MonteiroTi\Sei\Dto\TipoProcedimento;
use MonteiroTi\Sei\Dto\Unidade;
use MonteiroTi\Sei\Dto\Usuario;
use MonteiroTi\Sei\Enum\NivelAcesso;
use MonteiroTi\Sei\Operations\SeiOperations;
use MonteiroTi\Sei\SeiClient;

/**
 * Tabelas auxiliares (operações listar*).
 *
 * Unidades, séries e tipos de processo vêm filtrados pelo que está liberado para o serviço,
 * o que permite montar listas no sistema cliente só com valores válidos.
 */
class Catalogos extends Api
{
    public function __construct(SeiClient $client, private readonly ?CatalogCache $cache = null)
    {
        parent::__construct($client);
    }

    /** @return list<Unidade> */
    public function unidades(?string $idTipoProcedimento = null, ?string $idSerie = null): array
    {
        return $this->list('listarUnidades', ['IdTipoProcedimento' => $idTipoProcedimento, 'IdSerie' => $idSerie], Unidade::class);
    }

    /** @return list<Serie> */
    public function series(?string $idTipoProcedimento = null, ?string $idUnidade = null): array
    {
        return $this->list('listarSeries', ['IdUnidade' => $idUnidade, 'IdTipoProcedimento' => $idTipoProcedimento], Serie::class);
    }

    /** @return list<TipoProcedimento> */
    public function tiposProcedimento(?string $idSerie = null, ?bool $individual = null, ?string $idUnidade = null): array
    {
        return $this->list('listarTiposProcedimento', [
            'IdUnidade' => $idUnidade,
            'IdSerie' => $idSerie,
            'SinIndividual' => Values::sn($individual),
        ], TipoProcedimento::class);
    }

    /** @return list<TipoConferencia> */
    public function tiposConferencia(?string $idUnidade = null): array
    {
        return $this->list('listarTiposConferencia', ['IdUnidade' => $idUnidade], TipoConferencia::class);
    }

    /** @return list<TipoPrioridade> */
    public function tiposPrioridade(?string $idUnidade = null): array
    {
        return $this->list('listarTiposPrioridade', ['IdUnidade' => $idUnidade], TipoPrioridade::class);
    }

    /**
     * @param  NivelAcesso|null  $nivelAcesso  Restrito ou Sigiloso
     * @return list<HipoteseLegal>
     */
    public function hipotesesLegais(?NivelAcesso $nivelAcesso = null): array
    {
        return $this->list('listarHipotesesLegais', ['NivelAcesso' => $nivelAcesso?->value], HipoteseLegal::class);
    }

    /** @return list<Marcador> */
    public function marcadoresUnidade(): array
    {
        return $this->list('listarMarcadoresUnidade', [], Marcador::class);
    }

    /** @return list<Pais> */
    public function paises(): array
    {
        return $this->list('listarPaises', [], Pais::class);
    }

    /** @return list<Estado> */
    public function estados(?string $idPais = null): array
    {
        return $this->list('listarEstados', ['IdPais' => $idPais], Estado::class);
    }

    /** @return list<Cidade> */
    public function cidades(?string $idPais = null, ?string $idEstado = null): array
    {
        return $this->list('listarCidades', ['IdPais' => $idPais, 'IdEstado' => $idEstado], Cidade::class);
    }

    /** @return list<Cargo> */
    public function cargos(?string $idCargo = null): array
    {
        return $this->list('listarCargos', ['IdCargo' => $idCargo], Cargo::class);
    }

    /** @return list<Feriado> */
    public function feriados(string $idOrgao, DateTimeInterface $dataInicial, DateTimeInterface $dataFinal): array
    {
        return $this->list('listarFeriados', [
            'IdOrgao' => $idOrgao,
            'DataInicial' => Values::date($dataInicial),
            'DataFinal' => Values::date($dataFinal),
        ], Feriado::class);
    }

    /** @return list<ArquivoExtensao> */
    public function extensoesPermitidas(?string $idArquivoExtensao = null): array
    {
        return $this->list('listarExtensoesPermitidas', ['IdArquivoExtensao' => $idArquivoExtensao], ArquivoExtensao::class);
    }

    /**
     * Usuários com perfil "Básico" na unidade.
     *
     * @return list<Usuario>
     */
    public function usuarios(?string $idUsuario = null): array
    {
        return $this->list('listarUsuarios', ['IdUsuario' => $idUsuario], Usuario::class);
    }

    /**
     * @template T of \MonteiroTi\Sei\Core\Support\FromSoap
     *
     * @param  array<string, mixed>  $parameters
     * @param  class-string<T>  $class
     * @return list<T>
     */
    protected function list(string $operation, array $parameters, string $class): array
    {
        $fetch = fn (): array => SoapData::hydrateList($this->call($operation, $parameters), $class);

        if ($this->cache === null) {
            return $fetch();
        }

        $config = $this->client->config();
        $key = implode(':', [
            $config->siglaSistema,
            substr(hash('sha256', $config->identificacaoServico), 0, 12),
            SeiOperations::requiresUnidade($operation) ? ($config->idUnidade ?? '-') : '-',
            $operation,
            md5(serialize($parameters)),
        ]);

        return $this->cache->remember($key, $fetch);
    }
}

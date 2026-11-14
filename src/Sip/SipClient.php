<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Sip;

use MonteiroTi\Sei\Core\Config\SipConfig;
use MonteiroTi\Sei\Core\Config\TransportOptions;
use MonteiroTi\Sei\Core\Exceptions\InvalidArgumentException;
use MonteiroTi\Sei\Core\Support\SoapData;
use MonteiroTi\Sei\Core\Support\Values;
use MonteiroTi\Sei\Core\Transport\SoapTransport;
use MonteiroTi\Sei\Core\Transport\Transport;
use MonteiroTi\Sei\Sip\Dto\FiltroPermissao;
use MonteiroTi\Sei\Sip\Dto\Permissao;
use MonteiroTi\Sei\Sip\Dto\Usuario;

/**
 * Cliente do WebService do SIP: replicação de usuários e permissões.
 */
final class SipClient
{
    public function __construct(
        private readonly Transport $transport,
        private readonly SipConfig $config,
    ) {}

    /**
     * Chaves: wsdl, chave_acesso, id_sistema e as opções de transporte (timeout, verify_ssl, ...).
     *
     * @param  array<string, mixed>  $config
     */
    public static function create(array $config): self
    {
        return new self(new SoapTransport(TransportOptions::fromArray($config)), SipConfig::fromArray($config));
    }

    /**
     * listarPermissao, ordenada por órgão do usuário, IdUsuario e IdOrigemUsuario.
     *
     * @param  string|null  $idSistema  Padrão: id_sistema da conexão
     * @return list<Permissao>
     */
    public function listarPermissao(?FiltroPermissao $filtro = null, ?string $idSistema = null): array
    {
        $f = $filtro ?? new FiltroPermissao;
        $idSistema ??= $this->config->idSistema
            ?? throw new InvalidArgumentException('Informe idSistema ou configure id_sistema na conexão SIP.');

        $result = $this->call('listarPermissao', [
            'IdSistema' => $idSistema,
            'IdOrgaoUsuario' => $f->idOrgaoUsuario,
            'IdUsuario' => $f->idUsuario,
            'IdOrigemUsuario' => $f->idOrigemUsuario,
            'IdOrgaoUnidade' => $f->idOrgaoUnidade,
            'IdUnidade' => $f->idUnidade,
            'IdOrigemUnidade' => $f->idOrigemUnidade,
            'IdPerfil' => $f->idPerfil,
        ]);

        return SoapData::hydrateList($result, Permissao::class);
    }

    /**
     * replicarPermissao. Cada permissão é tratada em transação separada; erros acumulados
     * chegam como ValidationException. Permissões sem IdSistema recebem o da conexão.
     */
    public function replicarPermissao(Permissao ...$permissoes): void
    {
        $items = [];
        foreach ($permissoes as $permissao) {
            $soap = $permissao->toSoap();
            $soap['IdSistema'] ??= $this->config->idSistema;
            $items[] = $soap;
        }

        $this->call('replicarPermissao', ['Permissoes' => $items]);
    }

    /**
     * replicarUsuario.
     *
     * @param  list<Usuario>  $usuarios
     * @param  bool  $considerarOrgao  Considera IdOrgao junto com IdOrigem (mesmo usuário em vários órgãos)
     */
    public function replicarUsuario(array $usuarios, bool $considerarOrgao = false): void
    {
        $this->call('replicarUsuario', [
            'Usuarios' => Values::list($usuarios),
            'SinConsiderarOrgao' => Values::sn($considerarOrgao),
        ]);
    }

    /**
     * Chamada genérica (a chave de acesso é preenchida automaticamente).
     *
     * @param  array<string, mixed>  $parameters
     */
    public function call(string $operation, array $parameters = []): mixed
    {
        $names = SipOperations::parameters($operation);
        $unknown = array_diff(array_keys($parameters), $names);
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf('Parâmetro(s) desconhecido(s) para %s: %s.', $operation, implode(', ', $unknown)));
        }

        $parameters['ChaveAcesso'] = $this->config->chaveAcesso;

        $ordered = [];
        foreach ($names as $name) {
            $ordered[$name] = $parameters[$name] ?? null;
        }

        return $this->transport->call($operation, $ordered);
    }

    public function config(): SipConfig
    {
        return $this->config;
    }

    public function transport(): Transport
    {
        return $this->transport;
    }
}

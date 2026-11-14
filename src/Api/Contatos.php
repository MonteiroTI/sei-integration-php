<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Api;

use Generator;
use MonteiroTi\Sei\Core\Exceptions\InvalidArgumentException;
use MonteiroTi\Sei\Core\Support\SoapData;
use MonteiroTi\Sei\Core\Support\Values;
use MonteiroTi\Sei\Dto\Contato;
use MonteiroTi\Sei\Options\FiltroContatos;

/** Cadastro de contatos. */
final class Contatos extends Api
{
    public const MAX_POR_PAGINA = 1000;

    /**
     * listarContatos (uma página, ordenada por nome).
     *
     * @return list<Contato>
     */
    public function listar(?FiltroContatos $filtro = null, int $pagina = 1, int $porPagina = 100): array
    {
        if ($porPagina < 1 || $porPagina > self::MAX_POR_PAGINA) {
            throw new InvalidArgumentException('porPagina deve estar entre 1 e 1000.');
        }
        if ($pagina < 1) {
            throw new InvalidArgumentException('pagina deve ser maior ou igual a 1.');
        }

        $f = $filtro ?? new FiltroContatos;

        $result = $this->call('listarContatos', [
            'IdTipoContato' => $f->idTipoContato,
            'PaginaRegistros' => $porPagina,
            'PaginaAtual' => $pagina,
            'Sigla' => $f->sigla,
            'Nome' => $f->nome,
            'Cpf' => $f->cpf,
            'Cnpj' => $f->cnpj,
            'Matricula' => $f->matricula,
            'IdContatos' => $f->idContatos === [] ? null : Values::ids($f->idContatos),
        ]);

        return SoapData::hydrateList($result, Contato::class);
    }

    /**
     * Percorre todas as páginas de listarContatos.
     *
     * @return Generator<int, Contato>
     */
    public function todos(?FiltroContatos $filtro = null, int $porPagina = self::MAX_POR_PAGINA): Generator
    {
        $pagina = 1;
        do {
            $contatos = $this->listar($filtro, $pagina++, $porPagina);
            yield from $contatos;
        } while (count($contatos) === $porPagina);
    }

    /**
     * atualizarContatos. Cada contato é processado separadamente; erros são acumulados
     * e lançados ao final como ValidationException.
     */
    public function atualizar(Contato ...$contatos): void
    {
        $this->call('atualizarContatos', ['Contatos' => Values::list($contatos)]);
    }
}

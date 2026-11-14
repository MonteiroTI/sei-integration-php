<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Api;

use MonteiroTi\Sei\Core\Exceptions\InvalidArgumentException;
use MonteiroTi\Sei\Core\Support\SoapData;
use MonteiroTi\Sei\Core\Support\Values;
use MonteiroTi\Sei\Dto\Andamento;
use MonteiroTi\Sei\Dto\AndamentoMarcador;
use MonteiroTi\Sei\Dto\AtributoAndamento;

/** Histórico (andamentos) de processos. */
final class Andamentos extends Api
{
    /** Tarefa de "atualização de andamento" (texto livre no atributo DESCRICAO). */
    public const TAREFA_ATUALIZACAO_ANDAMENTO = 65;

    /**
     * listarAndamentos. Informe ao menos um filtro.
     *
     * @param  list<string>  $andamentos  IDs de andamentos
     * @param  list<int|string>  $tarefas  IDs de tarefas (ver constantes de TarefaRN no SEI)
     * @param  list<string>  $tarefasModulos  IDs de tarefas de módulos
     * @return list<Andamento>
     */
    public function listar(
        string $protocoloProcedimento,
        array $andamentos = [],
        array $tarefas = [],
        array $tarefasModulos = [],
        bool $retornarAtributos = false,
    ): array {
        if ($andamentos === [] && $tarefas === [] && $tarefasModulos === []) {
            throw new InvalidArgumentException('listarAndamentos exige ao menos um filtro: andamentos, tarefas ou tarefasModulos.');
        }

        $result = $this->call('listarAndamentos', [
            'ProtocoloProcedimento' => $protocoloProcedimento,
            'SinRetornarAtributos' => Values::sn($retornarAtributos),
            'Andamentos' => $andamentos === [] ? null : Values::ids($andamentos),
            'Tarefas' => $tarefas === [] ? null : Values::ids($tarefas),
            'TarefasModulos' => $tarefasModulos === [] ? null : Values::ids($tarefasModulos),
        ]);

        return SoapData::hydrateList($result, Andamento::class);
    }

    /**
     * lancarAndamento. IdTarefa deve ser 65 ou maior/igual a 1000; ou informe IdTarefaModulo.
     *
     * @param  list<AtributoAndamento>  $atributos
     */
    public function lancar(string $protocoloProcedimento, int|string|null $idTarefa = null, ?string $idTarefaModulo = null, array $atributos = []): Andamento
    {
        if ($idTarefa === null && $idTarefaModulo === null) {
            throw new InvalidArgumentException('Informe idTarefa ou idTarefaModulo.');
        }
        if ($idTarefa !== null) {
            $id = (int) $idTarefa;
            if ($id !== self::TAREFA_ATUALIZACAO_ANDAMENTO && $id < 1000) {
                throw new InvalidArgumentException('idTarefa deve ser 65 ou maior ou igual a 1000 (valores abaixo são reservados do SEI).');
            }
        }

        $result = $this->call('lancarAndamento', [
            'ProtocoloProcedimento' => $protocoloProcedimento,
            'IdTarefa' => $idTarefa === null ? null : (string) $idTarefa,
            'IdTarefaModulo' => $idTarefaModulo,
            'Atributos' => Values::list($atributos),
        ]);

        return Andamento::fromSoap(SoapData::from($result));
    }

    /** Atalho: lança uma "atualização de andamento" com texto livre (tarefa 65). */
    public function atualizar(string $protocoloProcedimento, string $descricao): Andamento
    {
        return $this->lancar($protocoloProcedimento, self::TAREFA_ATUALIZACAO_ANDAMENTO, null, [AtributoAndamento::descricao($descricao)]);
    }

    /**
     * listarAndamentosMarcadores.
     *
     * @param  list<string|null>  $marcadores  IDs de marcadores; inclua null para trazer também remoções
     * @return list<AndamentoMarcador>
     */
    public function listarMarcadores(string $protocoloProcedimento, array $marcadores = []): array
    {
        $result = $this->call('listarAndamentosMarcadores', [
            'ProtocoloProcedimento' => $protocoloProcedimento,
            'Marcadores' => $marcadores === [] ? null : array_map(fn ($m) => $m === null ? null : (string) $m, $marcadores),
        ]);

        return SoapData::hydrateList($result, AndamentoMarcador::class);
    }
}

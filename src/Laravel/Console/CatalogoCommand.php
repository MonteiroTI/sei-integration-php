<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Console;

use Illuminate\Console\Command;
use MonteiroTi\Sei\Core\Exceptions\SeiException;
use MonteiroTi\Sei\Laravel\SeiManager;

/**
 * Lista tabelas do SEI para ajudar a preencher IDs na configuração do sistema cliente.
 */
final class CatalogoCommand extends Command
{
    private const TIPOS = [
        'unidades' => 'Unidades liberadas para o serviço',
        'series' => 'Tipos de documento',
        'tipos-processo' => 'Tipos de processo',
        'tipos-conferencia' => 'Tipos de conferência',
        'tipos-prioridade' => 'Tipos de prioridade',
        'hipoteses-legais' => 'Hipóteses legais',
        'marcadores' => 'Marcadores da unidade',
        'usuarios' => 'Usuários da unidade',
        'extensoes' => 'Extensões de arquivo permitidas',
        'paises' => 'Países',
        'estados' => 'Estados',
        'cargos' => 'Cargos',
    ];

    protected $signature = 'sei:catalogo
        {tipo : unidades|series|tipos-processo|tipos-conferencia|tipos-prioridade|hipoteses-legais|marcadores|usuarios|extensoes|paises|estados|cargos}
        {--conexao= : Nome da conexão}
        {--unidade= : ID da unidade (padrão: id_unidade da conexão)}
        {--json : Saída em JSON}';

    protected $description = 'Lista tabelas auxiliares do SEI (IDs de unidades, tipos de processo, séries, ...)';

    public function handle(SeiManager $manager): int
    {
        $tipo = $this->argument('tipo');
        if (! is_string($tipo) || ! isset(self::TIPOS[$tipo])) {
            $this->components->error('Tipo inválido. Use: '.implode(', ', array_keys(self::TIPOS)));

            return self::INVALID;
        }

        $conexao = $this->option('conexao');
        $client = $manager->connection(is_string($conexao) ? $conexao : null);
        $unidade = $this->option('unidade');
        if (is_string($unidade) && $unidade !== '') {
            $client = $client->naUnidade($unidade);
        }
        $c = $client->catalogos(cache: false);

        try {
            $items = match ($tipo) {
                'unidades' => $c->unidades(),
                'series' => $c->series(),
                'tipos-processo' => $c->tiposProcedimento(),
                'tipos-conferencia' => $c->tiposConferencia(),
                'tipos-prioridade' => $c->tiposPrioridade(),
                'hipoteses-legais' => $c->hipotesesLegais(),
                'marcadores' => $c->marcadoresUnidade(),
                'usuarios' => $c->usuarios(),
                'extensoes' => $c->extensoesPermitidas(),
                'paises' => $c->paises(),
                'estados' => $c->estados(),
                'cargos' => $c->cargos(),
            };
        } catch (SeiException|\InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $rows = array_map(fn (object $item) => array_map(
            fn ($v) => $v instanceof \BackedEnum ? $v->value : (is_bool($v) ? ($v ? 'S' : 'N') : (is_scalar($v) || $v === null ? $v : json_encode($v))),
            array_filter(get_object_vars($item), fn ($v) => ! is_string($v) || strlen($v) < 120),
        ), $items);

        if ($this->option('json')) {
            $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->components->info(self::TIPOS[$tipo].': '.count($rows));
        if ($rows !== []) {
            $this->table(array_keys($rows[0]), $rows);
        }

        return self::SUCCESS;
    }
}

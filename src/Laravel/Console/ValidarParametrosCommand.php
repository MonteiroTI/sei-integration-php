<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Console;

use Illuminate\Console\Command;
use MonteiroTi\Sei\Core\Exceptions\SeiException;
use MonteiroTi\Sei\Laravel\SeiManager;

/**
 * Confere os IDs de connections.*.parametros contra os catálogos do SEI.
 */
final class ValidarParametrosCommand extends Command
{
    protected $signature = 'sei:parametros:validar {--conexao= : Nome da conexão}';

    protected $description = 'Confere se os IDs configurados em sei.connections.*.parametros existem no SEI';

    public function handle(SeiManager $manager): int
    {
        $conexao = $this->option('conexao');
        $nome = is_string($conexao) && $conexao !== '' ? $conexao : $manager->getDefaultConnection();

        try {
            $parametros = $manager->connectionConfig($nome)['parametros'] ?? [];
        } catch (\InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if (! is_array($parametros) || array_filter($parametros) === []) {
            $this->components->warn("Nenhum parâmetro configurado na conexão [{$nome}].");

            return self::SUCCESS;
        }

        $catalogos = $manager->connection($nome)->catalogos(cache: false);
        $linhas = [];
        $erros = 0;

        foreach ($parametros as $grupo => $valores) {
            if (! is_array($valores) || $valores === []) {
                continue;
            }

            try {
                $itens = GruposCatalogo::itens($catalogos, (string) $grupo);
            } catch (SeiException $e) {
                $this->components->error("{$grupo}: {$e->getMessage()}");
                $erros++;

                continue;
            }

            foreach ($valores as $chave => $id) {
                $id = is_scalar($id) ? (string) $id : '';
                if ($itens === null) {
                    $linhas[] = ["{$grupo}.{$chave}", $id, 'não verificado (grupo sem catálogo)'];
                } elseif (isset($itens[$id])) {
                    $linhas[] = ["{$grupo}.{$chave}", $id, 'ok — '.$itens[$id]];
                } else {
                    $linhas[] = ["{$grupo}.{$chave}", $id, 'NÃO ENCONTRADO (inexistente ou não liberado para o serviço)'];
                    $erros++;
                }
            }
        }

        $this->table(['Parâmetro', 'ID', 'Situação'], $linhas);

        if ($erros > 0) {
            $this->components->error("{$erros} parâmetro(s) com problema.");

            return self::FAILURE;
        }

        $this->components->info('Todos os parâmetros conferem com o SEI.');

        return self::SUCCESS;
    }
}

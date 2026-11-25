<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Console;

use Illuminate\Console\Command;
use MonteiroTi\Sei\Core\Config\TransportOptions;
use MonteiroTi\Sei\Core\Exceptions\SeiException;
use MonteiroTi\Sei\Core\Transport\SoapTransport;
use MonteiroTi\Sei\Laravel\SeiManager;
use MonteiroTi\Sei\Operations\SeiOperations;
use MonteiroTi\Sei\Operations\WsdlContract;
use MonteiroTi\Sei\Sip\SipOperations;

final class PingCommand extends Command
{
    protected $signature = 'sei:ping
        {conexao? : Nome da conexão (padrão: a default)}
        {--sip : Testa a conexão SIP em vez da SEI}';

    protected $description = 'Verifica a WSDL, a compatibilidade das operações e as credenciais de uma conexão SEI/SIP';

    public function handle(SeiManager $manager): int
    {
        $sip = (bool) $this->option('sip');
        $name = $this->argument('conexao');
        $name = is_string($name) ? $name : ($sip ? null : $manager->getDefaultConnection());

        try {
            $config = $sip ? $manager->sipConnectionConfig($name ?? 'default') : $manager->connectionConfig((string) $name);
            $transport = new SoapTransport(TransportOptions::fromArray($config));

            $this->components->task('Carregando WSDL '.$config['wsdl'], fn () => $transport->functions() !== []);

            $report = WsdlContract::compare(
                $transport->functions(),
                $sip ? SipOperations::all() : SeiOperations::all(),
                $sip ? SipOperations::parameters(...) : SeiOperations::parameters(...),
            );
            $this->report($report);

            if ($sip) {
                return $report['missing'] === [] && $report['mismatched'] === [] ? self::SUCCESS : self::FAILURE;
            }

            $client = $manager->connection($name);
            $circuito = $client->circuito();
            $abertoAte = $circuito?->abertoAte();
            if ($circuito !== null && $circuito->aberto() && $abertoAte !== null) {
                $this->components->warn(sprintf(
                    'Circuito aberto até %s: o SEI falhou nas últimas chamadas e novas chamadas estão suspensas.',
                    date('H:i:s', $abertoAte),
                ));
            }
            $permitidas = $client->operacoesPermitidas();
            if (count($permitidas) < count(SeiOperations::all())) {
                $this->components->info(sprintf('Operações liberadas nesta conexão (%d): %s', count($permitidas), implode(', ', $permitidas)));
            }

            if (! $client->config()->permite('listarUnidades')) {
                $this->components->warn('listarUnidades não está liberada nesta conexão: credenciais não verificadas.');
            } else {
                $unidades = $client->catalogos(cache: false)->unidades();
                $this->components->info(sprintf('Credenciais válidas: %d unidade(s) liberada(s) para o serviço.', count($unidades)));
            }
        } catch (SeiException|\InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return $report['missing'] === [] && $report['mismatched'] === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array{missing: list<string>, extra: list<string>, mismatched: array<string, array{expected: list<string>, actual: list<string>}>}  $report
     */
    private function report(array $report): void
    {
        if ($report['missing'] !== []) {
            $this->components->warn('Operações da biblioteca ausentes na WSDL (versão do SEI diferente?): '.implode(', ', $report['missing']));
        }
        if ($report['extra'] !== []) {
            $this->components->info('Operações da WSDL sem método na biblioteca (use call()): '.implode(', ', $report['extra']));
        }
        foreach ($report['mismatched'] as $operation => $diff) {
            $this->components->error("Parâmetros de {$operation} diferem da WSDL.");
            $this->line('  esperado: '.implode(', ', $diff['expected']));
            $this->line('  WSDL:     '.implode(', ', $diff['actual']));
        }
        if ($report['missing'] === [] && $report['mismatched'] === []) {
            $this->components->info('Todas as operações conferem com a WSDL.');
        }
    }
}

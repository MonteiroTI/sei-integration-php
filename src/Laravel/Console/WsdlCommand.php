<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Console;

use Illuminate\Console\Command;
use MonteiroTi\Sei\Core\Config\TransportOptions;
use MonteiroTi\Sei\Core\Exceptions\SeiException;
use MonteiroTi\Sei\Core\Transport\SoapTransport;
use MonteiroTi\Sei\Laravel\SeiManager;

/**
 * Baixa a WSDL de uma conexão (para conferência, versionamento ou wsdl local).
 */
final class WsdlCommand extends Command
{
    protected $signature = 'sei:wsdl
        {conexao? : Nome da conexão (padrão: a default)}
        {--sip : Usa a conexão SIP}
        {--saida= : Arquivo de destino (padrão: imprime na saída)}';

    protected $description = 'Baixa a WSDL do SEI ou do SIP de uma conexão';

    public function handle(SeiManager $manager): int
    {
        $sip = (bool) $this->option('sip');
        $nome = $this->argument('conexao');
        $nome = is_string($nome) && $nome !== '' ? $nome : ($sip ? (string) config('sei.sip.default', 'default') : $manager->getDefaultConnection());

        try {
            $config = $sip ? $manager->sipConnectionConfig($nome) : $manager->connectionConfig($nome);
            $wsdl = (new SoapTransport(TransportOptions::fromArray($config)))->downloadWsdl();
        } catch (SeiException|\InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $saida = $this->option('saida');
        if (! is_string($saida) || $saida === '') {
            $this->output->write($wsdl);

            return self::SUCCESS;
        }

        if (@file_put_contents($saida, $wsdl) === false) {
            $this->components->error("Não foi possível gravar {$saida}.");

            return self::FAILURE;
        }

        $this->components->info(sprintf('WSDL salva em %s (%d bytes).', $saida, strlen($wsdl)));

        return self::SUCCESS;
    }
}

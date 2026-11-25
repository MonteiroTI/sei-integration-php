<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Saude;

use MonteiroTi\Sei\Laravel\SeiManager;

/**
 * Listener do evento DiagnosingHealth (rota /up do Laravel): falha quando o circuito de alguma
 * conexão SEI monitorada está aberto, ou seja, quando o SEI deixou de responder.
 *
 * Não faz chamada de rede: só lê o estado do circuit breaker no cache. Ligue com
 * sei.saude.up = true (SEI_SAUDE_UP).
 */
final class VerificarCircuitos
{
    public function __construct(private readonly SeiManager $manager) {}

    public function handle(): void
    {
        $abertos = [];
        foreach ($this->manager->circuitos($this->conexoes()) as $nome => $circuito) {
            $ate = $circuito->abertoAte();
            if ($circuito->aberto() && $ate !== null) {
                $abertos[] = sprintf('%s (até %s)', $nome, date('H:i:s', $ate));
            }
        }

        if ($abertos !== []) {
            throw new SeiIndisponivelException('SEI indisponível: circuito aberto na(s) conexão(ões) '.implode(', ', $abertos).'.');
        }
    }

    /**
     * @return list<string>
     */
    private function conexoes(): array
    {
        $conexoes = config('sei.saude.conexoes');
        if (is_array($conexoes) && $conexoes !== []) {
            return array_values(array_map('strval', $conexoes));
        }

        return [$this->manager->getDefaultConnection()];
    }
}

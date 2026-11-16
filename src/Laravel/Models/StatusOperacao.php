<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Models;

enum StatusOperacao: string
{
    /** Em andamento (protegida por lock). */
    case Executando = 'executando';
    /** Concluída com sucesso: o resultado salvo é devolvido em novas tentativas. */
    case Concluida = 'concluida';
    /** O SEI recusou ou a chamada não chegou a ser enviada: pode ser repetida. */
    case Falhou = 'falhou';
    /** Pode ter sido executada no SEI (timeout após o envio): exige conferência manual. */
    case Incerta = 'incerta';
    /** Descartada manualmente após conferência. */
    case Descartada = 'descartada';
}

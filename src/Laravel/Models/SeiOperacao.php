<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Registro de idempotência de uma operação no SEI.
 *
 * @property int $id
 * @property string $chave
 * @property string $conexao
 * @property string $operacao
 * @property StatusOperacao $status
 * @property int $tentativas
 * @property string|null $resultado
 * @property string|null $erro
 * @property string|null $vinculavel_type
 * @property int|string|null $vinculavel_id
 * @property Carbon|null $iniciada_em
 * @property Carbon|null $concluida_em
 */
class SeiOperacao extends SeiModel
{
    protected const TABELA = 'operacoes';

    /** @var list<class-string>|null */
    private static ?array $classesPermitidas = null;

    protected function casts(): array
    {
        return [
            'status' => StatusOperacao::class,
            'tentativas' => 'integer',
            'iniciada_em' => 'datetime',
            'concluida_em' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function vinculavel(): MorphTo
    {
        return $this->morphTo();
    }

    public function guardarResultado(mixed $resultado): void
    {
        $this->resultado = serialize($resultado);
    }

    /** Resultado salvo, restrito às classes de DTO e enum da biblioteca. */
    public function lerResultado(): mixed
    {
        if ($this->resultado === null) {
            return null;
        }

        return unserialize($this->resultado, ['allowed_classes' => self::classesPermitidas()]);
    }

    /**
     * @return list<class-string>
     */
    private static function classesPermitidas(): array
    {
        if (self::$classesPermitidas !== null) {
            return self::$classesPermitidas;
        }

        $classes = [\DateTimeImmutable::class, \DateTimeZone::class, \stdClass::class];
        foreach (['Dto' => 'MonteiroTi\\Sei\\Dto\\', 'Enum' => 'MonteiroTi\\Sei\\Enum\\'] as $dir => $namespace) {
            foreach (glob(dirname(__DIR__, 2).'/'.$dir.'/*.php') ?: [] as $file) {
                $class = $namespace.basename($file, '.php');
                if (class_exists($class) || enum_exists($class)) {
                    $classes[] = $class;
                }
            }
        }

        return self::$classesPermitidas = $classes;
    }
}

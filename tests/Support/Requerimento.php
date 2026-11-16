<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use MonteiroTi\Sei\Laravel\Concerns\HasSeiProcesso;

/** Model de exemplo de um sistema que gera processos no SEI. */
class Requerimento extends Model
{
    use HasSeiProcesso;

    protected $guarded = [];
}

<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Testing;

use MonteiroTi\Sei\Core\Exceptions\SeiException;
use MonteiroTi\Sei\Core\Support\Redactor;
use MonteiroTi\Sei\Core\Transport\Transport;

/**
 * Grava cada chamada (operação, argumentos e resposta ou erro) em um JSON, para reproduzir
 * depois com FakeTransport::reproduzir() / Sei::fake()->reproduzir().
 *
 * Os arquivos vão para o repositório, então passam por mascaramento (LGPD):
 * - credenciais (IdentificacaoServico, ChaveAcesso) nunca são gravadas;
 * - dados pessoais saem como no log (CPF, CNPJ, e-mail, nomes de interessados, signatários e usuários);
 * - textos livres que costumam citar pessoas (Especificacao, Observacao, Descricao, Valor...)
 *   viram ***, e CPF, CNPJ e e-mail são mascarados em qualquer outro texto;
 * - conteúdos longos (Base64) são resumidos.
 *
 * Mesmo assim, revise as gravações antes de commitá-las.
 */
final class GravadorTransport implements Transport
{
    /** Textos livres mascarados por padrão nas gravações. */
    public const TEXTOS_LIVRES = ['Especificacao', 'Observacao', 'Observacoes', 'Descricao', 'Valor', 'Texto', 'Motivo'];

    private int $sequencia = 0;

    private readonly Redactor $redactor;

    /**
     * @param  string  $diretorio  Onde gravar ({nnn}-{operacao}.json)
     * @param  list<string>  $chavesExtras  Outras chaves a mascarar
     */
    public function __construct(
        private readonly Transport $inner,
        private readonly string $diretorio,
        array $chavesExtras = [],
    ) {
        if (! is_dir($diretorio) && ! mkdir($diretorio, 0775, true) && ! is_dir($diretorio)) {
            throw new \RuntimeException("Não foi possível criar o diretório de gravações {$diretorio}.");
        }

        $this->redactor = new Redactor(Redactor::MODO_MASCARAR, [...self::TEXTOS_LIVRES, ...$chavesExtras]);
        $this->sequencia = count(glob(rtrim($diretorio, '/').'/*.json') ?: []);
    }

    public function call(string $operation, array $arguments): mixed
    {
        try {
            $resposta = $this->inner->call($operation, $arguments);
        } catch (SeiException $e) {
            $this->gravar($operation, $arguments, ['erro' => [
                'classe' => $e::class,
                'mensagem' => $this->redactor->text($e->getMessage()),
            ]]);

            throw $e;
        }

        $this->gravar($operation, $arguments, ['resposta' => $this->limpar($resposta, $operation)]);

        return $resposta;
    }

    public function inner(): Transport
    {
        return $this->inner;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $resultado
     */
    private function gravar(string $operation, array $arguments, array $resultado): void
    {
        $registro = [
            'operacao' => $operation,
            'argumentos' => $this->limpar($arguments, $operation),
            ...$resultado,
        ];

        $arquivo = sprintf('%s/%03d-%s.json', rtrim($this->diretorio, '/'), ++$this->sequencia, $operation);
        file_put_contents($arquivo, json_encode($registro, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    }

    /** Converte objetos em arrays, mascara dados pessoais e textos com CPF, CNPJ ou e-mail. */
    private function limpar(mixed $valor, string $operation): mixed
    {
        $valor = self::normalizar($valor);
        if (is_array($valor)) {
            $valor = $this->redactor->arguments($valor, $operation);
        }

        return $this->mascararTextos($valor);
    }

    private static function normalizar(mixed $valor): mixed
    {
        if (is_object($valor)) {
            $valor = get_object_vars($valor);
        }

        return is_array($valor) ? array_map(self::normalizar(...), $valor) : $valor;
    }

    private function mascararTextos(mixed $valor): mixed
    {
        if (is_array($valor)) {
            return array_map($this->mascararTextos(...), $valor);
        }

        return is_string($valor) ? $this->redactor->text($valor) : $valor;
    }
}

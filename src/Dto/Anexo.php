<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Dto;

use DateTimeImmutable;
use DateTimeInterface;
use MonteiroTi\Sei\Core\Exceptions\InvalidArgumentException;
use MonteiroTi\Sei\Core\Support\ToSoap;
use MonteiroTi\Sei\Core\Support\Values;

/** Estrutura Anexo (anexos de uma manifestação de ouvidoria). */
final readonly class Anexo implements ToSoap
{
    /**
     * @param  string  $conteudo  Bytes do arquivo (sem Base64)
     */
    public function __construct(
        public string $nome,
        public string $conteudo,
        public ?DateTimeInterface $dataHora = null,
    ) {}

    public static function fromPath(string $path, ?string $nome = null): self
    {
        $conteudo = @file_get_contents($path);
        if ($conteudo === false) {
            throw new InvalidArgumentException("Não foi possível ler o arquivo {$path}.");
        }
        $mtime = @filemtime($path);

        return new self(
            $nome ?? basename($path),
            $conteudo,
            $mtime === false ? null : (new DateTimeImmutable('@'.$mtime))->setTimezone(Values::timezone()),
        );
    }

    public function toSoap(): array
    {
        return [
            'Nome' => $this->nome,
            'Tamanho' => strlen($this->conteudo),
            'DataHora' => Values::dateTime($this->dataHora ?? new DateTimeImmutable('now', Values::timezone())),
            'Conteudo' => base64_encode($this->conteudo),
        ];
    }
}

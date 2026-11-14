<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Api;

use MonteiroTi\Sei\Core\Exceptions\InvalidArgumentException;
use MonteiroTi\Sei\Core\Support\SoapData;
use MonteiroTi\Sei\Core\Support\Values;
use MonteiroTi\Sei\Dto\Documento;
use MonteiroTi\Sei\Dto\RetornoConsultaDocumento;
use MonteiroTi\Sei\Dto\RetornoInclusaoDocumento;
use MonteiroTi\Sei\Options\ConsultaDocumentoOptions;

/** Operações sobre documentos e envio de arquivos. */
final class Documentos extends Api
{
    /**
     * incluirDocumento em um processo existente.
     * O documento deve ter protocoloProcedimento ou idProcedimento (ver Documento::noProcesso()).
     */
    public function incluir(Documento $documento): RetornoInclusaoDocumento
    {
        if ($documento->protocoloProcedimento === null && $documento->idProcedimento === null) {
            throw new InvalidArgumentException('Informe o processo do documento (Documento::noProcesso() ou noProcessoId()).');
        }

        $result = $this->call('incluirDocumento', [
            'Documento' => $documento->toSoap($this->client->config()->conteudoCharset),
        ]);

        return RetornoInclusaoDocumento::fromSoap(SoapData::from($result));
    }

    /** consultarDocumento. Documentos de processos sigilosos não são retornados. */
    public function consultar(string $protocoloDocumento, ?ConsultaDocumentoOptions $options = null): RetornoConsultaDocumento
    {
        $o = $options ?? new ConsultaDocumentoOptions;

        $result = $this->call('consultarDocumento', [
            'ProtocoloDocumento' => $protocoloDocumento,
            'SinRetornarAndamentoGeracao' => Values::sn($o->andamentoGeracao),
            'SinRetornarAssinaturas' => Values::sn($o->assinaturas),
            'SinRetornarPublicacao' => Values::sn($o->publicacao),
            'SinRetornarCampos' => Values::sn($o->campos),
            'SinRetornarBlocos' => Values::sn($o->blocos),
        ]);

        return RetornoConsultaDocumento::fromSoap(SoapData::from($result));
    }

    public function cancelar(string $protocoloDocumento, string $motivo): void
    {
        $this->call('cancelarDocumento', ['ProtocoloDocumento' => $protocoloDocumento, 'Motivo' => $motivo]);
    }

    public function excluir(string $protocoloDocumento): void
    {
        $this->call('excluirDocumento', ['ProtocoloDocumento' => $protocoloDocumento]);
    }

    /**
     * adicionarArquivo: cria um arquivo no repositório do SEI com o primeiro trecho do conteúdo.
     *
     * @param  int  $tamanho  Tamanho total do arquivo em bytes
     * @param  string  $hash  MD5 (hex) do conteúdo total
     * @param  string  $conteudo  Trecho inicial (bytes, sem Base64)
     * @return string IdArquivo
     */
    public function adicionarArquivo(string $nome, int $tamanho, string $hash, string $conteudo): string
    {
        $result = $this->call('adicionarArquivo', [
            'Nome' => $nome,
            'Tamanho' => $tamanho,
            'Hash' => $hash,
            'Conteudo' => base64_encode($conteudo),
        ]);

        return (string) Values::id($result);
    }

    /**
     * adicionarConteudoArquivo: acrescenta um trecho ao arquivo. O SEI ativa o arquivo
     * quando tamanho e hash conferem.
     *
     * @param  string  $conteudo  Trecho (bytes, sem Base64)
     */
    public function adicionarConteudoArquivo(string $idArquivo, string $conteudo): string
    {
        $result = $this->call('adicionarConteudoArquivo', [
            'IdArquivo' => $idArquivo,
            'Conteudo' => base64_encode($conteudo),
        ]);

        return (string) (Values::id($result) ?? $idArquivo);
    }

    /**
     * Envia um arquivo em partes (adicionarArquivo + adicionarConteudoArquivo) e devolve o IdArquivo,
     * para uso em Documento::externo(idArquivo: ...). Evita mensagens SOAP enormes e dispensa MTOM.
     *
     * Arquivos não associados a um documento em 24 h são removidos pelo SEI.
     *
     * @param  string|resource  $source  Caminho do arquivo ou stream legível e posicionável
     * @param  string|null  $nome  Nome do arquivo (padrão: nome do caminho)
     * @param  int|null  $chunkSize  Bytes por parte (padrão: upload_chunk_size da conexão)
     * @return string IdArquivo
     */
    public function enviarArquivo(mixed $source, ?string $nome = null, ?int $chunkSize = null): string
    {
        $chunkSize ??= $this->client->config()->uploadChunkSize;
        if ($chunkSize < 1) {
            throw new InvalidArgumentException('chunkSize deve ser maior que zero.');
        }

        [$stream, $close] = $this->openStream($source);

        try {
            if ($nome === null) {
                if (! is_string($source)) {
                    throw new InvalidArgumentException('Informe o nome do arquivo ao enviar um stream.');
                }
                $nome = basename($source);
            }

            [$tamanho, $hash] = $this->measure($stream);
            if ($tamanho === 0) {
                throw new InvalidArgumentException('O arquivo está vazio.');
            }

            $idArquivo = $this->adicionarArquivo($nome, $tamanho, $hash, $this->read($stream, $chunkSize));

            while (! feof($stream)) {
                $chunk = $this->read($stream, $chunkSize);
                if ($chunk === '') {
                    break;
                }
                $this->adicionarConteudoArquivo($idArquivo, $chunk);
            }

            return $idArquivo;
        } finally {
            if ($close) {
                fclose($stream);
            }
        }
    }

    /**
     * @return array{0: resource, 1: bool}
     */
    private function openStream(mixed $source): array
    {
        if (is_resource($source)) {
            $meta = stream_get_meta_data($source);
            if (! $meta['seekable']) {
                throw new InvalidArgumentException('O stream precisa ser posicionável (seekable) para calcular tamanho e hash.');
            }

            return [$source, false];
        }

        if (! is_string($source) || ! is_file($source) || ! is_readable($source)) {
            throw new InvalidArgumentException('Arquivo não encontrado ou sem permissão de leitura: '.(is_string($source) ? $source : get_debug_type($source)));
        }

        $stream = fopen($source, 'rb');
        if ($stream === false) {
            throw new InvalidArgumentException("Não foi possível abrir o arquivo {$source}.");
        }

        return [$stream, true];
    }

    /**
     * @param  resource  $stream
     * @return array{0: int, 1: string}
     */
    private function measure($stream): array
    {
        rewind($stream);
        $context = hash_init('md5');
        $tamanho = hash_update_stream($context, $stream);
        rewind($stream);

        return [$tamanho, hash_final($context)];
    }

    /**
     * @param  resource  $stream
     * @param  int<1, max>  $length
     */
    private function read($stream, int $length): string
    {
        $buffer = '';
        while (! feof($stream)) {
            $remaining = $length - strlen($buffer);
            if ($remaining < 1) {
                break;
            }
            $part = fread($stream, $remaining);
            if ($part === false) {
                throw new InvalidArgumentException('Falha ao ler o arquivo.');
            }
            if ($part === '') {
                break;
            }
            $buffer .= $part;
        }

        return $buffer;
    }
}

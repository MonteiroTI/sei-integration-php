<?php

declare(strict_types=1);

namespace MonteiroTi\Sei\Core\Config;

/**
 * Credenciais e preferências de uma conexão com o WebService do SEI.
 */
final readonly class SeiConfig
{
    /**
     * @param  string  $siglaSistema  Sigla do sistema cadastrado em Administração/Sistemas
     * @param  string  $identificacaoServico  Chave de acesso gerada para o serviço (ou a identificação, se autenticação por endereço)
     * @param  string|null  $idUnidade  Unidade padrão usada quando a chamada não informa outra
     * @param  string  $conteudoCharset  Charset do HTML de documentos gerados antes do Base64 (UTF-8 ou ISO-8859-1)
     * @param  int  $uploadChunkSize  Tamanho (bytes, antes do Base64) de cada parte em Documentos::enviarArquivo()
     */
    public function __construct(
        public string $siglaSistema,
        public string $identificacaoServico,
        public ?string $idUnidade = null,
        public string $conteudoCharset = 'UTF-8',
        public int $uploadChunkSize = 5 * 1024 * 1024,
    ) {
        if ($uploadChunkSize < 1) {
            throw new \InvalidArgumentException('uploadChunkSize deve ser maior que zero.');
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        foreach (['sigla_sistema', 'identificacao_servico'] as $key) {
            if (empty($config[$key])) {
                throw new \InvalidArgumentException(sprintf('A opção "%s" é obrigatória.', $key));
            }
        }

        return new self(
            siglaSistema: (string) $config['sigla_sistema'],
            identificacaoServico: (string) $config['identificacao_servico'],
            idUnidade: isset($config['id_unidade']) && $config['id_unidade'] !== '' ? (string) $config['id_unidade'] : null,
            conteudoCharset: (string) ($config['conteudo_charset'] ?? 'UTF-8'),
            uploadChunkSize: (int) ($config['upload_chunk_size'] ?? 5 * 1024 * 1024),
        );
    }

    public function withUnidade(?string $idUnidade): self
    {
        return new self($this->siglaSistema, $this->identificacaoServico, $idUnidade, $this->conteudoCharset, $this->uploadChunkSize);
    }
}

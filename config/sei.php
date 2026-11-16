<?php

declare(strict_types=1);
use MonteiroTi\Sei\Laravel\Models\SeiDocumento;
use MonteiroTi\Sei\Laravel\Models\SeiOperacao;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;

return [

    /*
    |--------------------------------------------------------------------------
    | Conexão SEI padrão
    |--------------------------------------------------------------------------
    |
    | Cada conexão representa um par sistema/serviço cadastrado no SEI
    | (Administração > Sistemas > Serviços). Use várias conexões para
    | órgãos, ambientes (homologação/produção) ou serviços diferentes.
    |
    */

    'default' => env('SEI_CONNECTION', 'default'),

    'connections' => [

        'default' => [
            // https://sei.orgao.gov.br/sei/controlador_ws.php?servico=sei
            'wsdl' => env('SEI_WSDL'),
            // Sigla do sistema cadastrado no SEI
            'sigla_sistema' => env('SEI_SIGLA_SISTEMA'),
            // Chave de acesso gerada para o serviço (parâmetro IdentificacaoServico)
            'identificacao_servico' => env('SEI_IDENTIFICACAO_SERVICO'),
            // Unidade usada quando a chamada não informa outra (naUnidade())
            'id_unidade' => env('SEI_ID_UNIDADE'),

            // Endpoint alternativo ao declarado na WSDL (ex.: atrás de proxy reverso)
            'location' => env('SEI_LOCATION'),
            // Charset do HTML de documentos gerados antes do Base64: UTF-8 ou ISO-8859-1
            'conteudo_charset' => env('SEI_CONTEUDO_CHARSET', 'UTF-8'),
            'timeout' => (int) env('SEI_TIMEOUT', 60),
            'connection_timeout' => (int) env('SEI_CONNECTION_TIMEOUT', 10),
            'verify_ssl' => (bool) env('SEI_VERIFY_SSL', true),
            'wsdl_cache' => WSDL_CACHE_BOTH,
            // Tamanho de cada parte em Documentos::enviarArquivo() (bytes)
            'upload_chunk_size' => 5 * 1024 * 1024,
            'soap_options' => [],

            // Permissões mínimas: bloqueia localmente operações fora da lista (aceita curinga *).
            // Null libera todas. Libere também apenas essas operações no cadastro do serviço no SEI.
            'operacoes' => null,
            // true: apenas consultar* e listar*
            'somente_leitura' => false,

            // IDs do SEI usados pelo sistema, por nome (o manual recomenda não espalhar IDs pelo código).
            // Uso: Sei::parametro('tipos_processo.ferias'). Gere com: php artisan sei:catalogo tipos-processo --php
            // Confira com: php artisan sei:parametros:validar
            'parametros' => [
                'unidades' => [],
                'tipos_processo' => [],
                'series' => [],
                'hipoteses_legais' => [],
            ],
        ],

        /*
        | Exemplo: um serviço do SEI só de leitura, com chave própria, para consultas
        | e sincronização; a conexão "default" fica com as operações de escrita.
        |
        | 'leitura' => [
        |     'wsdl' => env('SEI_WSDL'),
        |     'sigla_sistema' => env('SEI_SIGLA_SISTEMA'),
        |     'identificacao_servico' => env('SEI_LEITURA_IDENTIFICACAO_SERVICO'),
        |     'id_unidade' => env('SEI_ID_UNIDADE'),
        |     'somente_leitura' => true,
        | ],
        */

    ],

    /*
    |--------------------------------------------------------------------------
    | SIP
    |--------------------------------------------------------------------------
    */

    'sip' => [
        'default' => env('SIP_CONNECTION', 'default'),

        'connections' => [
            'default' => [
                // https://sip.orgao.gov.br/sip/controlador_ws.php?servico=sip
                'wsdl' => env('SIP_WSDL'),
                'chave_acesso' => env('SIP_CHAVE_ACESSO'),
                'id_sistema' => env('SIP_ID_SISTEMA'),
                'timeout' => (int) env('SIP_TIMEOUT', 60),
                'verify_ssl' => (bool) env('SIP_VERIFY_SSL', true),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache dos catálogos (listarUnidades, listarSeries, listarTiposProcedimento, ...)
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'enabled' => (bool) env('SEI_CACHE', true),
        'store' => env('SEI_CACHE_STORE'),
        'ttl' => (int) env('SEI_CACHE_TTL', 86400),
        'prefix' => 'sei',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logs e eventos
    |--------------------------------------------------------------------------
    |
    | Os logs mascaram a chave de acesso e os dados pessoais (LGPD) e resumem
    | conteúdos em Base64.
    |
    */

    'log' => [
        'enabled' => (bool) env('SEI_LOG', false),
        'channel' => env('SEI_LOG_CHANNEL'),
        // Dados pessoais (CPF, CNPJ, e-mail, nomes de interessados, telefones, endereço...):
        // mascarar (padrão), remover ou manter. Credenciais são sempre mascaradas.
        'dados_pessoais' => env('SEI_LOG_DADOS_PESSOAIS', 'mascarar'),
        // Outras chaves a tratar como dado pessoal (ex.: campos de formulário)
        'chaves_extras' => [],
    ],

    'events' => (bool) env('SEI_EVENTS', true),

    /*
    |--------------------------------------------------------------------------
    | Integração de alto nível (Sei::integracao())
    |--------------------------------------------------------------------------
    |
    | Gera processos e inclui documentos no máximo uma vez por chave e grava os
    | vínculos no banco. Exige as tabelas do módulo de persistência.
    |
    */

    'integracao' => [
        // Nome do sistema usado nos textos abaixo
        'sistema' => env('SEI_SISTEMA_NOME', env('APP_NAME', 'Sistema')),
        // Identificador acrescentado à Observação do processo, para localizar o processo no SEI
        // em uma conferência manual. Placeholders: {sistema}, {tipo}, {id}. Null desliga.
        'identificador' => '[{sistema} {tipo} #{id}]',
        // Andamento de rastreabilidade (tarefa 65) após gerar processo ou incluir documento
        'andamento' => [
            'ativo' => (bool) env('SEI_ANDAMENTO_RASTREABILIDADE', false),
            'texto' => '{acao} pelo sistema {sistema} (ref. {tipo} #{id}).',
        ],
        // Store de cache usado para os locks de idempotência (precisa suportar locks)
        'lock_store' => env('SEI_LOCK_STORE'),
        'lock_segundos' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sincronização (polling)
    |--------------------------------------------------------------------------
    |
    | O SEI não notifica o sistema: php artisan sei:sincronizar consulta os
    | processos vinculados e dispara os eventos de MonteiroTi\Sei\Laravel\Events\Sincronizacao.
    |
    */

    'sincronizacao' => [
        // Conexão usada nas consultas: null (a do vínculo), um nome (ex.: 'leitura')
        // ou um mapa ['default' => 'leitura', 'orgao-b' => 'orgao-b-leitura']
        'conexao' => env('SEI_SINCRONIZACAO_CONEXAO'),
        // Segundos entre consultas de processos abertos e concluídos
        'intervalo' => (int) env('SEI_SINCRONIZACAO_INTERVALO', 900),
        'intervalo_concluidos' => (int) env('SEI_SINCRONIZACAO_INTERVALO_CONCLUIDOS', 86400),
        // Processos por execução de sei:sincronizar
        'limite' => 100,
        // Fila dos jobs de sincronização (null: a padrão)
        'fila' => env('SEI_SINCRONIZACAO_FILA'),
        // true: o pacote agenda sei:sincronizar no scheduler do Laravel
        'agendar' => (bool) env('SEI_SINCRONIZACAO_AGENDAR', false),
        'cron' => '*/15 * * * *',
    ],

    /*
    |--------------------------------------------------------------------------
    | Persistência (vínculos, idempotência e sincronização)
    |--------------------------------------------------------------------------
    |
    | Módulo opcional. Publique as migrations com:
    | php artisan vendor:publish --tag=sei-migrations
    |
    */

    'persistencia' => [
        // Conexão de banco (null: a padrão da aplicação)
        'conexao_banco' => env('SEI_DB_CONNECTION'),
        'tabelas' => [
            'processos' => 'sei_processos',
            'documentos' => 'sei_documentos',
            'operacoes' => 'sei_operacoes',
        ],
        // Substitua por classes próprias que estendam as do pacote, se precisar
        'models' => [
            'processo' => SeiProcesso::class,
            'documento' => SeiDocumento::class,
            'operacao' => SeiOperacao::class,
        ],
    ],

];

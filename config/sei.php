<?php

declare(strict_types=1);

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

];

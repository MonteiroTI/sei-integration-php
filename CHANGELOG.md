# Changelog

Todas as mudanças relevantes deste pacote. O formato segue o
[Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e as versões seguem
[SemVer](https://semver.org/lang/pt-BR/). Enquanto a versão for `0.x`, mudanças
incompatíveis podem ocorrer entre versões menores e serão listadas aqui.

## [Não lançado]

## [0.2.0] - 2026-11-25

Ainda não validada contra um SEI de homologação.

### Adicionado

- Novas tentativas automáticas de leituras (`consultar*`, `listar*`) após falha de conexão, com
  backoff exponencial e jitter (`retry` por conexão SEI e SIP; `RetryingTransport`, `RetryOptions`).
  Escritas nunca são repetidas pelo transporte.
- `SeiConfig::leitura()` e `SeiConfig::casa()` (curingas de operação) públicos.
- `config/sei.php` expõe `proxy_host`/`proxy_port` nas conexões SEI e as opções de transporte que
  faltavam na conexão SIP (`location`, `connection_timeout`, `wsdl_cache`, proxy, `soap_options`).
- Circuit breaker por conexão (`circuito`; `CircuitBreakerTransport`, `CircuitoAbertoException`):
  depois de falhas de conexão seguidas, as chamadas falham na hora por um tempo. No Laravel fica
  ligado por padrão, com estado no cache; `SeiClient::circuito()` e `SeiManager::circuitos()`.
- Andamentos intermediários na sincronização: com `sei.sincronizacao.andamentos.tarefas`, cada
  andamento dessas tarefas entre duas consultas gera um `NovoAndamentoSei` (`intermediario = true`).
- Testes: `MonteiroTi\Sei\Testing\Respostas` (respostas prontas no formato do SEI) e
  `SeiSimulado`, um SEI em memória ligado com `Sei::fake()->simular()` ou
  `FakeTransport::simular()`; `SeiFake::sei()` e `SeiFake::assertCalledInOrder()`.
- Gravação e reprodução de respostas: `GravadorTransport` grava chamadas reais com dados pessoais
  mascarados; `FakeTransport::reproduzir()` / `Sei::fake()->reproduzir()` as devolvem na mesma
  ordem. Novo script `composer test:gravar`.
- O mascaramento de dados pessoais também trata `Assinaturas`, `Usuario` e `UsuarioAtribuicao`
  como estruturas de pessoa.
- Health check: com `sei.saude.up` (`SEI_SAUDE_UP`), a rota `/up` do Laravel falha quando o
  circuito de uma conexão monitorada (`sei.saude.conexoes`) está aberto. `sei:ping` mostra o
  estado do circuito.

### Alterado

- `GerarProcessoSeiJob` e `IncluirDocumentoSeiJob` voltam à fila com backoff (até 5 tentativas)
  quando a falha é segura de repetir (conexão não enviada, circuito aberto, lock ocupado).
  Operações incertas e erros de negócio continuam falhando de vez; com `dispatchSync` a exceção
  é relançada.

- O `SoapTransport` só altera o `default_socket_timeout` quando ele é diferente do timeout da
  conexão. O README documenta o cuidado com o Octane/Swoole.

### Corrigido

- `uniqueId()` dos jobs de escrita usa a conexão padrão configurada (`sei.default`) em vez de
  `default` fixo quando a conexão não é informada.

## [0.1.0] - 2026-11-25

Primeira versão. Ainda não foi validada contra um SEI de homologação: a ordem dos
parâmetros segue o manual do WebService 5.0 e será conferida com a WSDL real.

### Adicionado

- Cliente do WebService SEI 5.0 (66 operações) e SIP (3 operações), com DTOs, enums,
  validação local, upload em partes e exceções tipadas.
- Camada Laravel: conexões nomeadas, facades `Sei`/`Sip`, cache de catálogos, eventos,
  `Sei::fake()` e os comandos `sei:ping`, `sei:catalogo` e `sei:wsdl`.
- Mascaramento de dados pessoais (LGPD) nos logs e em mensagens de erro
  (`sei.log.dados_pessoais`, `sei.log.chaves_extras`).
- Restrição de operações por conexão (`operacoes`, `somente_leitura`).
- Parâmetros nomeados por conexão (`parametros`, `Sei::parametro()`,
  `sei:catalogo --php`, `sei:parametros:validar`).
- Módulo de persistência opcional (migration `sei-migrations`): vínculos
  `SeiProcesso`/`SeiDocumento`, trait `HasSeiProcesso` e ledger `SeiOperacao`.
- Idempotência: `Sei::integracao()`, `GerarProcessoSeiJob`, `IncluirDocumentoSeiJob`,
  `sei:operacoes` e `sei:operacoes:resolver`; `ConnectionException::$possivelmenteEnviada`.
- Andamento de rastreabilidade opcional após gerar processo ou incluir documento.
- Sincronização por polling (`sei:sincronizar`, `SincronizarProcessoSeiJob`) com eventos
  de andamento, tramitação, conclusão, reabertura, assinatura e publicação.
- Ferramentas de validação na homologação: `.env.homologacao.example`,
  `composer wsdl:baixar` e teste de contrato com as WSDLs reais do SEI e do SIP.
- Skill do Claude Code com o guia de integração em sistemas Laravel
  (`php artisan vendor:publish --tag=sei-claude`).
- Compatível com Laravel 12 e 13 (mínimo: Laravel 12).

[Não lançado]: https://github.com/MonteiroTI/sei-integration-php/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/MonteiroTI/sei-integration-php/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/MonteiroTI/sei-integration-php/releases/tag/v0.1.0

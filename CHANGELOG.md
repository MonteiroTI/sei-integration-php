# Changelog

Todas as mudanças relevantes deste pacote. O formato segue o
[Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e as versões seguem
[SemVer](https://semver.org/lang/pt-BR/). Enquanto a versão for `0.x`, mudanças
incompatíveis podem ocorrer entre versões menores e serão listadas aqui.

## [Não lançado]

### Adicionado

- Novas tentativas automáticas de leituras (`consultar*`, `listar*`) após falha de conexão, com
  backoff exponencial e jitter (`retry` por conexão SEI e SIP; `RetryingTransport`, `RetryOptions`).
  Escritas nunca são repetidas pelo transporte.
- `SeiConfig::leitura()` e `SeiConfig::casa()` (curingas de operação) públicos.
- Circuit breaker por conexão (`circuito`; `CircuitBreakerTransport`, `CircuitoAbertoException`):
  depois de falhas de conexão seguidas, as chamadas falham na hora por um tempo. No Laravel fica
  ligado por padrão, com estado no cache; `SeiClient::circuito()` e `SeiManager::circuitos()`.

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

[Não lançado]: https://github.com/MonteiroTI/sei-integration-php/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/MonteiroTI/sei-integration-php/releases/tag/v0.1.0

---
name: sei-integration
description: Guia para integrar sistemas Laravel ao SEI/SIP com o pacote monteiroti/sei-integration. Use ao criar ou alterar código que gera processos, inclui documentos, tramita, consulta ou acompanha processos no SEI; ao configurar config/sei.php e .env; ao escrever jobs, listeners ou testes que envolvem o SEI; ou ao diagnosticar erros do WebService do SEI (AccessDeniedException, OperacaoIncertaException, parâmetros, charset).
---

# Integração Laravel ↔ SEI com `monteiroti/sei-integration`

Este guia é para quem (pessoa ou agente) vai escrever código de integração num sistema Laravel. Leia as seções 1 a 3 antes de escrever código. As demais são referência.

## 1. Conceitos do SEI que aparecem no código

| Termo no SEI | No código | Observação |
|---|---|---|
| Processo (procedimento) | `Procedimento`, `processos()` | Tem **ID interno** (`IdProcedimento`, ex.: `1210000000774`) e **número formatado** (`ProcedimentoFormatado`, ex.: `12.1.000000077-4`). Quase todas as operações usam o número formatado (`protocoloProcedimento`). |
| Documento gerado | `Documento::gerado()` | Criado no editor do SEI a partir de HTML. Tipo `G`. |
| Documento externo | `Documento::externo()` | Arquivo recebido (PDF etc.). Tipo `R`. Exige data, nome do arquivo e remetente. |
| Tipo de processo | `idTipoProcedimento` | ID numérico do SEI. Nunca escreva no código: use `Sei::parametro()`. |
| Série | `idSerie` | É o **tipo de documento** (Ofício, Despacho…). |
| Unidade | `idUnidade`, `naUnidade()` | Setor do órgão. As operações rodam "em nome" de uma unidade; o processo precisa estar aberto nela. |
| Andamento | `Andamento`, `andamentos()` | Registro do histórico do processo. |
| Nível de acesso | `NivelAcesso::Publico/Restrito/Sigiloso` | Restrito e sigiloso exigem hipótese legal (`idHipoteseLegal`). Processos **sigilosos não são retornados** pelas consultas. |
| Interessado / remetente / destinatário | `ContatoRef` | O SEI procura o contato por IdContato, CPF, CNPJ, Sigla+Nome… e cadastra se não achar. Sempre informe o nome. |
| Sistema / serviço / chave | `sigla_sistema`, `identificacao_servico` | Cadastrados em Administração › Sistemas. Cada serviço libera só algumas operações, unidades e tipos. |

## 2. Qual API usar

```
Precisa criar algo no SEI a partir de um registro do sistema (processo, documento, envio)?
  └─ SIM → Sei::integracao()   (idempotente, grava vínculo no banco, nunca duplica)
       ├─ gerarProcesso($model, Procedimento, [Documento...], ?GerarProcedimentoOptions, ?chave)
       ├─ incluirDocumento($model|SeiProcesso, Documento, chave)
       ├─ enviarProcesso(SeiProcesso, [idUnidade...], chave, ?EnviarProcessoOptions)
       └─ concluirProcesso(SeiProcesso, ?chave)
  └─ NÃO, só consulta ou operação sem vínculo → Sei::processos(), documentos(), andamentos(), catalogos()...

Precisa reagir a mudanças feitas no SEI (tramitação, conclusão, assinatura, publicação)?
  └─ Ouça os eventos de MonteiroTi\Sei\Laravel\Events\Sincronizacao (gerados por sei:sincronizar).
     Não crie polling próprio.

Precisa de um ID do SEI (tipo de processo, série, unidade)?
  └─ Sei::parametro('grupo.nome'), configurado em config/sei.php → connections.*.parametros.
```

Use a API "crua" (`Sei::processos()->gerar()`) para criar registros só em scripts pontuais. Num fluxo de aplicação, um timeout pode duplicar o processo.

## 3. Regras que não podem ser quebradas

1. **Nada de IDs fixos no código.** Use `Sei::parametro('tipos_processo.ferias')`. Gere a config com `php artisan sei:catalogo tipos-processo --php` e confira com `php artisan sei:parametros:validar`.
2. **Chaves de idempotência estáveis, derivadas do domínio**, ex.: `"despacho-{$despacho->id}"`, `"envio-{$tramite->id}"`. Nunca use UUID novo, timestamp ou `now()` na chave de uma operação que cria algo: cada tentativa viraria uma operação nova.
3. **Nunca repita uma `OperacaoIncertaException`.** Ela significa que o SEI pode ter criado o registro. Não capture para tentar de novo. Deixe falhar, avise, e a conferência é feita com `php artisan sei:operacoes` e `sei:operacoes:resolver`.
4. **Conteúdo sem Base64.** `Documento::gerado(conteudo: '<p>...</p>')` recebe HTML; `Documento::externo(conteudo: $bytes)` recebe os bytes. A biblioteca codifica.
5. **Arquivos grandes:** envie antes com `Sei::documentos()->enviarArquivo($caminho)` e use `Documento::externo(idArquivo: $id)`. Não coloque bytes de arquivos em payloads de fila.
6. **IDs do SEI são `string`.** Passam de 32 bits. Não converta para `int`.
7. **Não chame `\SoapClient` diretamente** nem monte arrays SOAP à mão: use os DTOs. Para uma operação sem método, use `Sei::call('operacao', ['Param' => ...])`.
8. **Unidade:** a operação roda na unidade da conexão (`id_unidade`) ou na informada em `naUnidade($id)`. Para tramitar, concluir ou incluir documento, o processo precisa estar aberto nessa unidade.
9. **Não registre em log** o retorno bruto do SEI nem mensagens de exceção com dados pessoais sem mascarar. Use `app(\MonteiroTi\Sei\Laravel\SeiManager::class)->redactor()->text($mensagem)`.
10. **Datas** são `DateTimeInterface` (Carbon serve). Flags `S`/`N` do SEI são `bool`. A conversão é automática.

## 4. Passo a passo para integrar num sistema Laravel

### 4.1 Instalação e configuração

```json
// composer.json do sistema
"repositories": [{ "type": "vcs", "url": "https://github.com/MonteiroTI/sei-integration-php" }]
```

```bash
composer require monteiroti/sei-integration:^0.1
php artisan vendor:publish --tag=sei-config
php artisan vendor:publish --tag=sei-migrations   # módulo de persistência (vínculo, idempotência, sincronização)
php artisan migrate
```

`.env`:

```dotenv
SEI_WSDL=https://sei.orgao.gov.br/sei/controlador_ws.php?servico=sei
SEI_SIGLA_SISTEMA=SISTEMA
SEI_IDENTIFICACAO_SERVICO=            # chave de acesso do serviço (segredo)
SEI_ID_UNIDADE=110000001              # unidade padrão
SEI_SISTEMA_NOME="${APP_NAME}"        # aparece no identificador e no andamento de rastreabilidade
SEI_ANDAMENTO_RASTREABILIDADE=true    # opcional
SEI_SINCRONIZACAO_AGENDAR=true        # opcional: agenda sei:sincronizar
SEI_LOG=true                          # opcional: dados pessoais saem mascarados
```

Confira a conexão: `php artisan sei:ping`.

Se o SEI do órgão tiver um serviço só de leitura, crie uma conexão `leitura` com `'somente_leitura' => true` e use-a na sincronização: `SEI_SINCRONIZACAO_CONEXAO=leitura`.

### 4.2 Parâmetros

```bash
php artisan sei:catalogo tipos-processo --php
php artisan sei:catalogo series --php
php artisan sei:catalogo unidades --php
```

Cole os trechos em `config/sei.php` → `connections.default.parametros`, renomeie as chaves para nomes do domínio e rode `php artisan sei:parametros:validar`.

### 4.3 Model do sistema

```php
use MonteiroTi\Sei\Laravel\Concerns\HasSeiProcesso;

class Requerimento extends Model
{
    use HasSeiProcesso;   // seiProcesso(), seiProcessos(), temProcessoSei()
}
```

### 4.4 Service de domínio (padrão recomendado)

Concentre a montagem dos DTOs num service do sistema. Controllers e jobs chamam o service.

```php
namespace App\Services\Sei;

use App\Models\Requerimento;
use MonteiroTi\Sei\Dto\{Assunto, ContatoRef, Documento, Procedimento};
use MonteiroTi\Sei\Enum\NivelAcesso;
use MonteiroTi\Sei\Laravel\Facades\Sei;
use MonteiroTi\Sei\Laravel\Models\SeiProcesso;

final class RequerimentoSei
{
    public function abrirProcesso(Requerimento $req): SeiProcesso
    {
        return Sei::integracao()->gerarProcesso(
            $req,
            new Procedimento(
                idTipoProcedimento: Sei::parametro('tipos_processo.requerimento'),
                especificacao: "Requerimento {$req->numero} - {$req->assunto}",
                interessados: [ContatoRef::cpf($req->cpf, $req->nome)],
                nivelAcesso: NivelAcesso::Restrito,
                idHipoteseLegal: Sei::parametro('hipoteses_legais.informacao_pessoal'),
            ),
            [
                Documento::gerado(
                    idSerie: Sei::parametro('series.requerimento'),
                    conteudo: view('sei.requerimento', ['req' => $req])->render(),
                ),
            ],
        );
    }

    public function anexarComprovante(Requerimento $req, string $caminho): void
    {
        $idArquivo = Sei::documentos()->enviarArquivo($caminho);

        Sei::integracao()->incluirDocumento(
            $req,
            Documento::externo(
                idSerie: Sei::parametro('series.comprovante'),
                data: now(),
                nomeArquivo: basename($caminho),
                remetente: ContatoRef::cpf($req->cpf, $req->nome),
                idArquivo: $idArquivo,
            ),
            chave: "comprovante-{$req->id}-".md5_file($caminho),
        );
    }
}
```

### 4.5 Em fila

```php
use MonteiroTi\Sei\Laravel\Jobs\{GerarProcessoSeiJob, IncluirDocumentoSeiJob};

GerarProcessoSeiJob::dispatch($req, $procedimento, $documentos);           // único por registro
IncluirDocumentoSeiJob::dispatch($req, $documento, "despacho-{$despacho->id}");
```

Os jobs repetem sozinhos (até 5 tentativas, com backoff) só o que é seguro: `ConnectionException` que chega ao job (a idempotência já converteu as duvidosas em `OperacaoIncertaException`), `CircuitoAbertoException` e `OperacaoEmAndamentoException`. `OperacaoIncertaException` e erros de negócio (`FaultException`, `AccessDeniedException`) fazem o job falhar de vez. **Não** sobrescreva `$tries`/`backoff` para repetir operações incertas: elas exigem conferência (`sei:operacoes`).

### 4.6 Reagir a mudanças no SEI

```php
// app/Listeners/AtualizarStatusRequerimento.php
use MonteiroTi\Sei\Laravel\Events\Sincronizacao\{ProcessoSeiConcluido, ProcessoSeiTramitado, DocumentoSeiAssinado};

final class AtualizarStatusRequerimento
{
    public function handle(ProcessoSeiConcluido $e): void
    {
        $req = $e->processo->vinculavel;   // o model do sistema (HasSeiProcesso)
        if ($req instanceof \App\Models\Requerimento) {
            $req->update(['status' => 'concluido_no_sei']);
        }
    }
}
```

| Evento | Propriedades |
|---|---|
| `NovoAndamentoSei` | `processo`, `andamento`, `intermediario`. Sempre há um evento para o último andamento; os intermediários só aparecem para as tarefas listadas em `sei.sincronizacao.andamentos.tarefas` (o `listarAndamentos` do SEI exige filtro por tarefa) |
| `ProcessoSeiTramitado` | `processo`, `antes`, `depois` (IDs de unidades), `entrou()`, `saiu()` |
| `ProcessoSeiConcluido` | `processo`, `andamentoConclusao` |
| `ProcessoSeiReaberto` | `processo` |
| `DocumentoSeiAssinado` | `documento`, `novas` (`list<Assinatura>`) |
| `PublicacaoSeiAgendada` / `DocumentoSeiPublicado` | `documento`, `publicacao` |
| `SincronizacaoSeiFalhou` | `registro` (`SeiProcesso` ou `SeiDocumento`), `exception`. A sincronização do registro foi desligada. |

A sincronização roda com `sei:sincronizar` (agendado pelo pacote se `SEI_SINCRONIZACAO_AGENDAR=true`). Os jobs vão para a fila: é preciso um worker rodando. A primeira consulta de cada processo só registra o estado, sem eventos.

### 4.7 Exibir dados na tela

Use o que está salvo no banco, sem chamar o SEI a cada requisição:

```php
$p = $req->seiProcesso;
$p?->protocolo_formatado;   // 12.1.000000077-4
$p?->link_acesso;           // link para abrir no SEI
$p?->aberto;                // bool, atualizado pela sincronização
$p?->unidades_abertas;      // [['id' => ..., 'sigla' => ..., 'usuario' => ...]]
$p?->documentos;            // SeiDocumento: documento_formatado, assinaturas, assinado_em, publicacao_estado
```

Se os usuários não tiverem login no SEI, o serviço precisa da opção **Gerar Links de Acesso Externos**, senão o `link_acesso` não abre para eles.

Para dados que não ficam salvos, consulte sob demanda (de preferência com cache):

```php
use MonteiroTi\Sei\Options\ConsultaProcedimentoOptions;

$consulta = Sei::processos()->consultar($p->protocolo_formatado, new ConsultaProcedimentoOptions(interessados: true, assuntos: true));
```

Cada flag `SinRetornar*` custa processamento no SEI: peça só o necessário.

## 5. Tratamento de erros

| Exceção (`MonteiroTi\Sei\...`) | Causa | O que o código deve fazer |
|---|---|---|
| `Core\Exceptions\InvalidArgumentException` | Dado inválido detectado antes da chamada | Bug ou validação do formulário: corrija os dados. |
| `Core\Exceptions\AccessDeniedException` | Operação, unidade ou tipo não liberados no serviço do SEI, ou fora de `operacoes` da conexão | Configuração: libere no cadastro do serviço no SEI. Não repita. |
| `Core\Exceptions\ValidationException` | Erros acumulados (`atualizarContatos`, `replicar*`) | Mostre `->errors()`. |
| `Core\Exceptions\FaultException` | Regra de negócio do SEI (processo não encontrado, já concluído...) | Mostre `->getMessage()` (mascarada) ao usuário ou registre. |
| `Core\Exceptions\ConnectionException` | Rede, timeout, WSDL | Se `possivelmenteEnviada === false`, pode repetir. Se `true` numa operação que cria algo, use `Sei::integracao()`, que transforma o caso em incerto. |
| `Laravel\Integracao\OperacaoIncertaException` | Pode ter sido executada no SEI | **Não repita.** Avise e deixe para `sei:operacoes`. |
| `Laravel\Integracao\OperacaoEmAndamentoException` | Mesma chave em execução | Aguarde e tente depois (é seguro). |

Todas herdam de `Core\Exceptions\SeiException` (`->operation` traz o nome da operação).

## 6. Testes no sistema

```php
use MonteiroTi\Sei\Laravel\Facades\Sei;

it('abre o processo do requerimento', function () {
    $sei = Sei::fake()->respond('gerarProcedimento', [
        'IdProcedimento' => '1210000000774',
        'ProcedimentoFormatado' => '12.1.000000077-4',
        'LinkAcesso' => 'https://sei.test/p',
        'RetornoInclusaoDocumentos' => [['IdDocumento' => '1', 'DocumentoFormatado' => '0000001']],
    ]);
    $req = Requerimento::factory()->create();

    app(RequerimentoSei::class)->abrirProcesso($req);

    $sei->assertCalled('gerarProcedimento', fn (array $a) => $a['Procedimento']['IdTipoProcedimento'] === config('sei.connections.default.parametros.tipos_processo.requerimento'));
    expect($req->seiProcesso->protocolo_formatado)->toBe('12.1.000000077-4');
});
```

- `Sei::fake()` troca o transporte de todas as conexões (SEI e SIP). Nada chega à rede.
- As respostas usam os **nomes de campo do SEI** (`IdProcedimento`, `ProcedimentoFormatado`...), como o `\SoapClient` devolveria. Também podem ser uma exceção (lançada) ou uma `Closure(array $args)`.
- Várias respostas para a mesma operação são devolvidas em ordem; a última se repete.
- Asserções: `assertCalled($op, ?fn)`, `assertCalledTimes($op, $n)`, `assertNotCalled($op)`, `assertNothingCalled()`. Os argumentos recebidos são nomeados como no manual, já com `SiglaSistema`, `IdentificacaoServico` e `IdUnidade`.
- Para testar timeout: `->respond('gerarProcedimento', new \MonteiroTi\Sei\Core\Exceptions\ConnectionException('timeout', 'gerarProcedimento', possivelmenteEnviada: true))` e espere `OperacaoIncertaException`.
- Para testar a sincronização: crie um `SeiProcesso`, responda `consultarProcedimento` duas vezes (a primeira só registra o estado) e use `Event::fake([...eventos de Sincronizacao])`.
- Prefira `MonteiroTi\Sei\Testing\Respostas` a montar arrays à mão: `Respostas::processoGerado('12.1.000000077-4', documentos: [Respostas::documentoIncluido('0000001')])`, `Respostas::processo(...)`, `Respostas::documento(...)`, `Respostas::andamento(...)`, `Respostas::assinatura(...)`.
- Para fluxos com várias chamadas, use o SEI simulado: `$fake = Sei::fake()->simular()`. Ele gera protocolos (`12.1.000000001-1`, documentos `0000001`...) e responde às consultas com o que foi criado. Mude o estado com `$fake->sei()->tramitar($protocolo, '110000015')`, `->assinar('0000001', 'Maria')`, `->concluir($protocolo)` e `->lancarAndamento(...)`; confira a ordem com `assertCalledInOrder([...])`. Respostas de `respond()` têm prioridade sobre o simulador.

## 7. Diagnóstico rápido

| Sintoma | Verifique |
|---|---|
| `AccessDeniedException` em toda chamada | Sigla, chave (`identificacao_servico`) e se o serviço usa **Chave de Acesso**. Rode `php artisan sei:ping`. |
| `AccessDeniedException` numa operação | Operação liberada no serviço do SEI? Está em `operacoes` da conexão? Unidade e tipo liberados para essa operação? |
| Catálogo vazio (`listarSeries`, `listarUnidades`) | Os catálogos só trazem o que está liberado para o serviço. |
| "Parâmetro SEI [x] não configurado" | Falta a chave em `connections.*.parametros`. |
| "exige uma unidade" | Configure `id_unidade` ou use `naUnidade()`. |
| Acentos trocados no documento gerado | `SEI_CONTEUDO_CHARSET=ISO-8859-1` (padrão: UTF-8). |
| Parâmetros chegando trocados | Rode `php artisan sei:ping`: ele compara a ordem dos parâmetros com a WSDL do SEI. |
| Processo não sincroniza | `sincronizar = false`? Veja `erro_sincronizacao`. Processos sigilosos não sincronizam. Há worker de fila? |
| Operações presas como incertas | `php artisan sei:operacoes` e `sei:operacoes:resolver "<chave>" --protocolo=... | --documento=... | --concluida | --descartar`. |

## 8. Referência rápida

### Clientes e APIs

- `Sei::connection(?nome)` → `SeiClient`; `Sei::naUnidade($id)`; `Sei::processos()` etc. usam a conexão padrão.
- `processos()`: `gerar`, `consultar`, `consultarIndividual`, `enviar`, `concluir`, `reabrir`, `bloquear`, `desbloquear`, `excluir`, `atribuir`, `anexar`, `desanexar`, `relacionar`, `removerRelacionamento`, `sobrestar`, `removerSobrestamento`, `registrarAnotacao`, `definirMarcador`, `definirControlePrazo`, `concluirControlePrazo`, `removerControlePrazo`, `enviarEmail`.
- `documentos()`: `incluir`, `consultar`, `cancelar`, `excluir`, `enviarArquivo`, `adicionarArquivo`, `adicionarConteudoArquivo`.
- `andamentos()`: `listar` (exige filtro de andamentos, tarefas ou tarefas de módulo), `lancar`, `atualizar` (texto livre, tarefa 65), `listarMarcadores`.
- `blocos()`, `publicacoes()`, `contatos()` (`todos()` pagina sozinho), `ouvidoria()`.
- `catalogos(cache: true)`: `unidades`, `series`, `tiposProcedimento`, `tiposConferencia`, `tiposPrioridade`, `hipotesesLegais`, `marcadoresUnidade`, `paises`, `estados`, `cidades`, `cargos`, `feriados`, `extensoesPermitidas`, `usuarios`.
- `Sip::listarPermissao()`, `Sip::replicarPermissao()`, `Sip::replicarUsuario()`.

### DTOs de entrada mais usados (`MonteiroTi\Sei\Dto`, `MonteiroTi\Sei\Options`)

- `new Procedimento(idTipoProcedimento, especificacao?, assuntos[], interessados[], observacao?, nivelAcesso?, idHipoteseLegal?, numeroProtocolo?, dataAutuacao?, idTipoPrioridade?)`
- `Documento::gerado(idSerie, conteudo?, descricao?, protocoloProcedimento?, ..., secoes[], campos[])`
- `Documento::externo(idSerie, data, nomeArquivo, remetente, conteudo? | idArquivo?, ...)`; `->noProcesso($protocolo)`
- `ContatoRef::cpf($cpf, $nome)`, `::cnpj()`, `::nome()`, `::id()`
- `new Assunto('00.01.01.01')`
- `GerarProcedimentoOptions(unidadesEnvio[], manterAbertoUnidade?, idMarcador?, diasControlePrazo?...)`
- `EnviarProcessoOptions(manterAbertoUnidade?, enviarEmailNotificacao?, diasRetornoProgramado?, reabrir?...)`
- `ConsultaProcedimentoOptions(...)` / `ConsultaDocumentoOptions(...)`, com `::completo()`
- `DefinicaoControlePrazo::emDias($protocolo, 5, uteis: true)`, `DefinicaoMarcador`, `Anotacao`, `Email`

### Comandos Artisan

`sei:ping`, `sei:wsdl`, `sei:catalogo {tipo} [--php|--json]`, `sei:parametros:validar`, `sei:operacoes [--status=]`, `sei:operacoes:resolver`, `sei:sincronizar [--processo=] [--agora]`.

### Configuração (`config/sei.php`)

`default`, `connections.*` (`wsdl`, `sigla_sistema`, `identificacao_servico`, `id_unidade`, `operacoes`, `somente_leitura`, `parametros`, `conteudo_charset`, `timeout`, `verify_ssl`...), `sip.*`, `cache.*`, `log.*` (`dados_pessoais`), `events`, `integracao.*` (`identificador`, `andamento`, `lock_store`), `sincronizacao.*` (`conexao`, `intervalo`, `agendar`), `persistencia.*` (tabelas, models).

Detalhes completos: `vendor/monteiroti/sei-integration/README.md`.

# sei-integration

Cliente PHP para os WebServices SOAP do **SEI** e do **SIP** (versão 5.0), com integração opcional ao **Laravel**.

- Cobre todas as **66 operações do SEI** e as **3 do SIP** do manual *SEI WebServices v5.0*.
- Tem DTOs tipados (`readonly`), enums e validação local antes de cada chamada.
- O núcleo não depende de framework. A camada Laravel oferece ServiceProvider, facades `Sei`/`Sip`, várias conexões nomeadas, cache de catálogos, logs com dados pessoais mascarados (LGPD), eventos, comandos Artisan e `Sei::fake()` para testes.
- Envia arquivos grandes em partes (`adicionarArquivo` + `adicionarConteudoArquivo`), sem precisar de MTOM.
- Restringe operações por conexão (`operacoes`, `somente_leitura`).
- Tem um módulo opcional de persistência: vínculo dos registros do sistema com processos e documentos, **idempotência** (um timeout não gera processo duplicado) e **sincronização** de status com eventos.

> O módulo [mod-sei-protocolo-integrado](https://github.com/pengovbr/mod-sei-protocolo-integrado) roda dentro do SEI e só envia metadados ao Protocolo Integrado. Ele não expõe endpoints para sistemas externos, por isso não faz parte desta biblioteca. Processos criados por ela chegam ao PI pelo próprio SEI, se o órgão usar o módulo.

## Requisitos

- PHP 8.2 ou superior, com `ext-soap` e `ext-mbstring`
- Laravel 11 ou 12 (opcional)

## Instalação

O pacote é distribuído pelo GitHub. No `composer.json` da aplicação:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/MonteiroTI/sei-integration-php" }
]
```

```bash
composer require monteiroti/sei-integration:^0.1
```

As versões seguem [SemVer](https://semver.org/lang/pt-BR/) e estão no [CHANGELOG](CHANGELOG.md). Enquanto a versão for `0.x`, mudanças incompatíveis podem ocorrer entre versões menores.

No Laravel, o provider é registrado sozinho. Para publicar a configuração:

```bash
php artisan vendor:publish --tag=sei-config
```

## 1. Cadastro no SEI

O manual descreve o cadastro no capítulo 1. Em resumo:

1. Em **Administração › Sistemas**, cadastre o sistema cliente. A *sigla* informada vira o `SiglaSistema`.
2. No ícone **Serviços** do sistema, cadastre um serviço e marque **Autenticação: Chave de Acesso**.
   - A autenticação por *Endereço* será removida em versões futuras do SEI.
   - Marque **Gerar Links de Acesso Externos** se os usuários do seu sistema não tiverem login no SEI. Sem isso, o `LinkAcesso` devolvido pelo SEI só abre para quem tem login e permissão; com a opção, o processo ou documento abre como no "Acesso Externo".
3. No ícone **Operações** do serviço, libere **só** as operações que o sistema vai usar. Você pode restringi-las por unidade, tipo de processo e tipo de documento. Prefira serviços separados por finalidade (ex.: um de leitura e outro de escrita), cada um com sua chave — veja [Segurança e LGPD](#6-segurança-e-lgpd).
4. Use **Gerar Chave de Acesso** e guarde a chave. Ela só aparece nesse momento. Ela é o `identificacao_servico`.

Para o SIP, cadastre o sistema e gere a chave de acesso. Se for usar `replicarUsuario`/`replicarPermissao`, libere esses serviços em **Serviços Liberados para Acesso no SIP**.

## 2. Configuração (Laravel)

`.env`:

```dotenv
SEI_WSDL=https://sei.orgao.gov.br/sei/controlador_ws.php?servico=sei
SEI_SIGLA_SISTEMA=MEUSISTEMA
SEI_IDENTIFICACAO_SERVICO=chave-de-acesso-do-servico
SEI_ID_UNIDADE=110000001

SIP_WSDL=https://sip.orgao.gov.br/sip/controlador_ws.php?servico=sip
SIP_CHAVE_ACESSO=chave-de-acesso-do-sip
SIP_ID_SISTEMA=100000100
```

Para usar várias conexões (outro órgão, homologação, outro serviço com outras operações), acrescente entradas em `config/sei.php`:

```php
'connections' => [
    'default' => [/* ... */],
    'homologacao' => [
        'wsdl' => env('SEI_HML_WSDL'),
        'sigla_sistema' => env('SEI_HML_SIGLA_SISTEMA'),
        'identificacao_servico' => env('SEI_HML_IDENTIFICACAO_SERVICO'),
        'id_unidade' => env('SEI_HML_ID_UNIDADE'),
    ],
],
```

| Opção | Padrão | Descrição |
|---|---|---|
| `wsdl` | — | URL da WSDL (`controlador_ws.php?servico=sei`) |
| `sigla_sistema` | — | Sigla do sistema cadastrado no SEI |
| `identificacao_servico` | — | Chave de acesso do serviço |
| `id_unidade` | `null` | Unidade padrão. Pode ser trocada por chamada com `naUnidade()` |
| `location` | `null` | Endpoint no lugar do declarado na WSDL (proxy reverso) |
| `conteudo_charset` | `UTF-8` | Charset do HTML de documentos gerados antes do Base64 (`UTF-8` ou `ISO-8859-1`) |
| `timeout` / `connection_timeout` | `60` / `10` | Tempos máximos, em segundos |
| `verify_ssl` | `true` | Valida o certificado do servidor |
| `upload_chunk_size` | 5 MB | Tamanho de cada parte em `enviarArquivo()` |
| `soap_options` | `[]` | Opções extras para o `\SoapClient` |

### IDs do SEI por nome (`parametros`)

O manual recomenda guardar os IDs de unidades, tipos de processo e tipos de documento em parâmetros do sistema, não no código:

```php
'connections' => [
    'default' => [
        // ...
        'parametros' => [
            'unidades' => ['protocolo' => '110000001'],
            'tipos_processo' => ['ferias' => '100000368'],
            'series' => ['portaria' => '3', 'oficio' => '301'],
        ],
    ],
],
```

```php
new Procedimento(Sei::parametro('tipos_processo.ferias'), 'Requerimento de férias');
```

### Comandos

```bash
php artisan sei:ping                  # WSDL, compatibilidade das operações, operações liberadas e credenciais
php artisan sei:ping homologacao
php artisan sei:ping --sip
php artisan sei:wsdl --saida=storage/app/sei.wsdl
php artisan sei:catalogo unidades     # IDs para preencher sua configuração
php artisan sei:catalogo tipos-processo --php     # trecho pronto para 'parametros'
php artisan sei:catalogo series --unidade=110000001 --json
php artisan sei:parametros:validar    # confere os IDs de 'parametros' contra o SEI
```

## 3. Uso

### Gerar processo com documentos

```php
use MonteiroTi\Sei\Dto\{Assunto, ContatoRef, Documento, Procedimento};
use MonteiroTi\Sei\Enum\NivelAcesso;
use MonteiroTi\Sei\Laravel\Facades\Sei;
use MonteiroTi\Sei\Options\GerarProcedimentoOptions;

$ret = Sei::processos()->gerar(
    new Procedimento(
        idTipoProcedimento: '100000368',
        especificacao: 'Requerimento de férias',
        assuntos: [new Assunto('00.01.01.01')],
        interessados: [ContatoRef::cpf('052.559.530-91', 'Paulo')],
        nivelAcesso: NivelAcesso::Publico,
    ),
    documentos: [
        Documento::gerado(idSerie: '3', conteudo: '<p>Texto do requerimento</p>'),
        Documento::externo(
            idSerie: '301',
            data: now(),
            nomeArquivo: 'anexo.pdf',
            remetente: ContatoRef::nome('Paulo'),
            conteudo: file_get_contents($caminho),   // bytes, sem Base64
        ),
    ],
    options: new GerarProcedimentoOptions(unidadesEnvio: ['110000015'], diasControlePrazo: 10),
);

$ret->procedimentoFormatado;              // 12.1.000000077-4
$ret->linkAcesso;
$ret->documentos[0]->documentoFormatado;  // na mesma ordem dos documentos enviados
```

O conteúdo dos documentos é sempre informado **sem Base64**: a biblioteca faz a codificação. Num documento gerado, informe o HTML da seção principal, ou use `secoes: [new SecaoDocumento('Ementa', '...')]`.

### Incluir documento em processo existente

```php
$doc = Sei::documentos()->incluir(
    Documento::gerado('3', conteudo: '<p>Despacho</p>')->noProcesso('12.1.000000077-4')
);
```

### Arquivos grandes

```php
$idArquivo = Sei::documentos()->enviarArquivo(storage_path('app/processo.pdf'));

Sei::documentos()->incluir(
    Documento::externo('301', now(), 'processo.pdf', ContatoRef::nome('Sistema X'), idArquivo: $idArquivo)
        ->noProcesso('12.1.000000077-4')
);
```

`enviarArquivo()` também aceita um stream posicionável (com o nome no 2º argumento). O SEI apaga arquivos que não forem associados a um documento em até 24 h.

### Consultar, tramitar e concluir

```php
use MonteiroTi\Sei\Options\{ConsultaProcedimentoOptions, EnviarProcessoOptions};

$processo = Sei::processos()->consultar('12.1.000000077-4', new ConsultaProcedimentoOptions(
    ultimoAndamento: true,
    unidadesProcedimentoAberto: true,
));
$processo->aberto();
$processo->ultimoAndamento?->descricao;

Sei::processos()->enviar('12.1.000000077-4', ['110000015'], new EnviarProcessoOptions(enviarEmailNotificacao: true));
Sei::naUnidade('110000015')->processos()->concluir('12.1.000000077-4');
```

Cada flag `SinRetornar*` custa processamento extra no SEI. Peça só o que for usar, ou use `ConsultaProcedimentoOptions::completo()`.

### Andamentos, marcadores e prazos

```php
Sei::andamentos()->atualizar('12.1.000000077-4', 'Pagamento confirmado pelo sistema X'); // tarefa 65
Sei::andamentos()->listar('12.1.000000077-4', tarefas: [1000], retornarAtributos: true);

Sei::processos()->definirMarcador(new DefinicaoMarcador('12.1.000000077-4', idMarcador: '15', texto: 'Aguardando'));
Sei::processos()->definirControlePrazo(DefinicaoControlePrazo::emDias('12.1.000000077-4', 5, uteis: true));
```

### Blocos, publicações, contatos e ouvidoria

```php
$idBloco = Sei::blocos()->gerar(TipoBloco::Assinatura, 'Assinar portarias', documentos: ['0003934']);
Sei::blocos()->disponibilizar($idBloco);

Sei::publicacoes()->agendar(
    new DadosPublicacao(MotivoPublicacao::Publicacao, idVeiculoPublicacao: '3', dataDisponibilizacao: now()->addDay()),
    protocoloDocumento: '0003934',
);

foreach (Sei::contatos()->todos(new FiltroContatos(cpf: '05255953091')) as $contato) { /* ... */ }

Sei::ouvidoria()->registrar(new ManifestacaoOuvidoria(idOrgao: '0', idTipoProcedimento: '100000400',
    nome: 'Maria', email: 'maria@exemplo.com', mensagem: '...', cpf: '...'));
```

### Catálogos (com cache)

```php
Sei::catalogos()->unidades();
Sei::catalogos()->series(idTipoProcedimento: '100000368');
Sei::catalogos()->tiposProcedimento();
Sei::catalogos()->hipotesesLegais(NivelAcesso::Restrito);
Sei::catalogos(cache: false)->usuarios();
```

As operações `listar*` ficam em cache por conexão e unidade. Ajuste em `sei.cache` (padrão: 24 h no store padrão).

### SIP

```php
use MonteiroTi\Sei\Laravel\Facades\Sip;
use MonteiroTi\Sei\Sip\Dto\{Permissao, Usuario};
use MonteiroTi\Sei\Sip\Enum\{OperacaoPermissao, OperacaoUsuario};

Sip::replicarUsuario([new Usuario(OperacaoUsuario::Cadastrar, idOrigem: '123', idOrgao: '0',
    sigla: 'fulano', nome: 'Fulano de Tal', cpf: '...', email: 'fulano@orgao.gov.br')]);

Sip::replicarPermissao(new Permissao(OperacaoPermissao::Atualizar, idOrgaoUsuario: '0', idOrigemUsuario: '123',
    idOrgaoUnidade: '0', idOrigemUnidade: 'RH', idPerfil: '100000938', dataInicial: now()));
```

### Injeção de dependência e conexões

```php
public function __construct(private SeiClient $sei) {}   // conexão padrão

Sei::connection('homologacao')->processos()->consultar('...');
app(SeiManager::class)->connection('orgao-b');
```

### Sem Laravel

```php
$sei = SeiClient::create([
    'wsdl' => 'https://sei.orgao.gov.br/sei/controlador_ws.php?servico=sei',
    'sigla_sistema' => 'MEUSISTEMA',
    'identificacao_servico' => 'chave',
    'id_unidade' => '110000001',
]);

$sip = SipClient::create(['wsdl' => '...?servico=sip', 'chave_acesso' => '...', 'id_sistema' => '...']);
```

### Chamada genérica

Para operações de módulos ou de versões futuras:

```php
Sei::call('concluirProcesso', ['ProtocoloProcedimento' => '12.1.000000077-4']);
```

`SiglaSistema`, `IdentificacaoServico` e `IdUnidade` são preenchidos automaticamente. Nomes de parâmetro desconhecidos geram erro.

## 4. Erros

Todas as exceções herdam de `MonteiroTi\Sei\Core\Exceptions\SeiException`, que traz a propriedade `operation`:

| Exceção | Quando |
|---|---|
| `ConnectionException` | WSDL inacessível, timeout, falha de rede ou SSL |
| `AccessDeniedException` | Sistema, serviço, chave, operação, unidade ou tipo não liberados (no SEI ou nas `operacoes` da conexão) |
| `ValidationException` | Erros acumulados de `atualizarContatos`, `replicarUsuario` e `replicarPermissao` (`->errors()`) |
| `FaultException` | Demais erros de negócio do SEI (`->messages()`, `->faultCode`) |
| `InvalidArgumentException` | Dados inválidos detectados antes da chamada |
| `OperacaoIncertaException` | Módulo de persistência: a operação pode ter sido executada no SEI; exige conferência (`sei:operacoes`) |
| `OperacaoEmAndamentoException` | Módulo de persistência: outra execução com a mesma chave está em andamento |

## 5. Testes na sua aplicação

```php
use MonteiroTi\Sei\Laravel\Facades\Sei;

$sei = Sei::fake()
    ->respond('gerarProcedimento', ['IdProcedimento' => '1', 'ProcedimentoFormatado' => '12.1.000000001-1'])
    ->respond('enviarProcesso', true);

// ... executa o código da aplicação ...

$sei->assertCalled('gerarProcedimento', fn (array $args) => $args['Procedimento']['IdTipoProcedimento'] === '100000368')
    ->assertCalledTimes('enviarProcesso', 1)
    ->assertNotCalled('excluirProcesso');
```

As respostas são o que o `\SoapClient` devolveria: arrays ou objetos com os nomes do SEI. Também podem ser uma `Closure` ou uma exceção a lançar. O fake cobre as conexões SEI e SIP.

## 6. Segurança e LGPD

### Permissões mínimas

No SEI, libere para cada serviço só as operações, unidades e tipos que o sistema usa. Na biblioteca, declare o mesmo na conexão: operações fora da lista são recusadas **localmente** com `AccessDeniedException`, sem chegar à rede.

```php
'connections' => [
    'escrita' => [
        // ... chave do serviço de escrita
        'operacoes' => ['gerarProcedimento', 'incluirDocumento', 'adicionar*', 'lancarAndamento', 'enviarProcesso'],
    ],
    'leitura' => [
        // ... chave do serviço de leitura (outro serviço no SEI)
        'somente_leitura' => true,   // apenas consultar* e listar*
    ],
],
```

`Sei::connection('leitura')->operacoesPermitidas()` e `php artisan sei:ping leitura` mostram o que está liberado.

### Chaves de acesso

- Guarde as chaves só em variáveis de ambiente ou num cofre de segredos (Vault, AWS Secrets Manager, Azure Key Vault), nunca no repositório.
- Uma chave por serviço: se vazar, troque só aquela (**Gerar Chave de Acesso** invalida a anterior).
- Não use a autenticação por *Endereço*: ela será removida do SEI.

### Logs (dados pessoais)

Com `SEI_LOG=true`, cada chamada é registrada com duração e argumentos, no canal `SEI_LOG_CHANNEL`:

- credenciais (`IdentificacaoServico`, `ChaveAcesso`) sempre aparecem como `***`;
- dados pessoais são mascarados: CPF `***.***.***-91`, CNPJ `**.***.***/****-32`, e-mail `p***@dominio`, nomes de interessados/remetentes/destinatários/contatos como iniciais, telefone, endereço, RG, matrícula e passaporte como `***`, mensagens como `[N caracteres]`;
- mensagens de erro do SEI passam pelo mesmo mascaramento de CPF, CNPJ e e-mail;
- conteúdos Base64 são resumidos.

| Opção | Padrão | Descrição |
|---|---|---|
| `sei.log.dados_pessoais` | `mascarar` | `mascarar`, `remover` (omite o campo) ou `manter` |
| `sei.log.chaves_extras` | `[]` | Outras chaves a tratar como dado pessoal |

### Eventos

`SeiRequestSending` (argumentos mascarados), `SeiResponseReceived` e `SeiRequestFailed`, em `MonteiroTi\Sei\Laravel\Events`, servem para métricas e auditoria. Os dois últimos trazem o retorno e a exceção **originais**: não os registre sem mascarar (`app(SeiManager::class)->redactor()`).

## 7. Módulo de persistência: vínculo, idempotência e sincronização

Opcional. Publique e rode a migration:

```bash
php artisan vendor:publish --tag=sei-migrations
php artisan migrate
```

Ela cria `sei_processos`, `sei_documentos` e `sei_operacoes` (nomes, conexão de banco e models configuráveis em `sei.persistencia`).

### Vínculo com os models do sistema

```php
use MonteiroTi\Sei\Laravel\Concerns\HasSeiProcesso;

class Requerimento extends Model
{
    use HasSeiProcesso;
}

$requerimento->seiProcesso?->protocolo_formatado;
$requerimento->seiProcesso?->link_acesso;
$requerimento->seiProcesso?->documentos;
$requerimento->temProcessoSei();
```

### Gerar processos sem duplicar (idempotência)

`gerarProcedimento` cria um processo novo a cada chamada. Se a resposta se perder (timeout depois do envio), repetir a chamada duplicaria o processo — e o WebService não tem busca por identificador externo. `Sei::integracao()` resolve isso:

```php
$processo = Sei::integracao()->gerarProcesso(
    $requerimento,
    new Procedimento(Sei::parametro('tipos_processo.ferias'), 'Requerimento de férias'),
    [Documento::gerado(Sei::parametro('series.requerimento'), conteudo: $html)],
);

Sei::integracao()->incluirDocumento($requerimento, $documento, chave: "despacho-{$despacho->id}");
Sei::integracao()->enviarProcesso($processo, ['110000015'], chave: "envio-{$tramite->id}");
```

- Cada operação tem uma **chave** (padrão para `gerarProcesso`: um processo por registro e conexão). Uma chave concluída devolve o resultado salvo, sem chamar o SEI de novo.
- Falha antes do envio (host recusou, DNS, WSDL) ou erro do SEI: a operação pode ser repetida.
- Timeout depois do envio, em operação que cria registros: a operação fica **incerta** e lança `OperacaoIncertaException`. Ela não é repetida até alguém conferir no SEI:

```bash
php artisan sei:operacoes                       # lista as incertas
php artisan sei:operacoes:resolver "<chave>" --protocolo=12.1.000000077-4   # o processo existe: cria o vínculo
php artisan sei:operacoes:resolver "<chave>" --descartar                     # não foi criado: libera nova tentativa
```

Para facilitar a conferência, a biblioteca acrescenta à **Observação** do processo um identificador do sistema (`[RH Requerimento #123]`, configurável em `sei.integracao.identificador`).

Em fila, use os jobs (sem retry automático para não duplicar):

```php
GerarProcessoSeiJob::dispatch($requerimento, $procedimento, $documentos);
IncluirDocumentoSeiJob::dispatch($requerimento, $documento, "despacho-{$despacho->id}");
```

Prefira `Documento::externo(idArquivo: ...)` (enviando o arquivo antes com `enviarArquivo()`) a colocar bytes de arquivos no payload da fila.

### Rastreabilidade no SEI

Com `SEI_ANDAMENTO_RASTREABILIDADE=true`, após gerar o processo ou incluir um documento a biblioteca lança um andamento (tarefa 65) no histórico, ex.: *"Processo gerado pelo sistema RH (ref. Requerimento #123)."* O texto é configurável em `sei.integracao.andamento.texto`. Uma falha nesse andamento não desfaz a operação principal.

### Sincronização de status

O SEI não notifica o sistema cliente. `sei:sincronizar` consulta os processos vinculados e dispara eventos (`MonteiroTi\Sei\Laravel\Events\Sincronizacao`):

| Evento | Quando |
|---|---|
| `NovoAndamentoSei` | O último andamento mudou (andamentos intermediários entre duas consultas não aparecem) |
| `ProcessoSeiTramitado` | Mudaram as unidades onde o processo está aberto (`entrou()`, `saiu()`) |
| `ProcessoSeiConcluido` / `ProcessoSeiReaberto` | O processo foi concluído em todas as unidades / reaberto |
| `DocumentoSeiAssinado` | O documento recebeu novas assinaturas |
| `PublicacaoSeiAgendada` / `DocumentoSeiPublicado` | Agendamento e publicação do documento |
| `SincronizacaoSeiFalhou` | O SEI recusou a consulta; a sincronização do registro foi desligada |

```php
// routes/console.php (ou SEI_SINCRONIZACAO_AGENDAR=true para o pacote agendar sozinho)
Schedule::command('sei:sincronizar')->everyFifteenMinutes()->withoutOverlapping();
```

- Processos abertos são consultados a cada 15 min e concluídos a cada 24 h (`sei.sincronizacao.intervalo*`).
- Use uma conexão só de leitura nas consultas: `SEI_SINCRONIZACAO_CONEXAO=leitura`.
- A primeira consulta de cada processo só registra o estado, sem eventos.
- `php artisan sei:sincronizar --processo=12.1.000000077-4 --agora` sincroniza um processo na hora.

### Processos sigilosos

As consultas do WebService não retornam processos sigilosos. Processos gerados com `NivelAcesso::Sigiloso` ficam marcados como `sigiloso` e fora da sincronização: o sistema deve guardar o que precisar no momento em que os cria (o vínculo já guarda o que o SEI devolve).

## 8. Decisões e pontos a validar

- **Encoding.** Por padrão a aplicação trabalha em UTF-8 e o `\SoapClient` envia UTF-8. O manual usa `'encoding' => 'ISO-8859-1'` porque o próprio SEI é ISO-8859-1 internamente. Num app UTF-8 isso causaria conversão dupla, por isso essa opção só existe como configuração (`encoding`).
- **Conteúdo de documentos gerados.** O exemplo do manual (`utf8_encode`) indica HTML em UTF-8 dentro do Base64. Se os acentos aparecerem trocados no editor do SEI, use `SEI_CONTEUDO_CHARSET=ISO-8859-1`. **Confirme na homologação** (há um teste de integração para isso).
- **Ordem dos parâmetros.** A WSDL é RPC: os parâmetros são posicionais. A ordem segue o manual 5.0 e foi conferida com o cliente público `rsei`. Rode `php artisan sei:ping` contra o seu SEI, ou salve a WSDL em `tests/Fixtures/sei-5.0.wsdl`, para conferir operação por operação.
- **IDs.** São sempre `string`, porque passam de 32 bits. Flags `S`/`N` viram `bool` e datas viram `DateTimeImmutable` (fuso `America/Sao_Paulo`).
- **MTOM.** Não é suportado (o `\SoapClient` nativo não tem MTOM). Use `enviarArquivo()`.

## 9. Mapa operação → método

### SEI

| Operação | Método |
|---|---|
| adicionarArquivo / adicionarConteudoArquivo | `documentos()->adicionarArquivo()` / `adicionarConteudoArquivo()` / `enviarArquivo()` |
| agendarPublicacao / alterarPublicacao | `publicacoes()->agendar()` / `alterar()` |
| cancelarAgendamentoPublicacao | `publicacoes()->cancelarAgendamento()` |
| confirmarDisponibilizacaoPublicacao | `publicacoes()->confirmarDisponibilizacao()` |
| consultarPublicacao | `publicacoes()->consultar()` |
| anexarProcesso / desanexarProcesso | `processos()->anexar()` / `desanexar()` |
| atribuirProcesso | `processos()->atribuir()` |
| bloquearProcesso / desbloquearProcesso | `processos()->bloquear()` / `desbloquear()` |
| concluirProcesso / reabrirProcesso | `processos()->concluir()` / `reabrir()` |
| consultarProcedimento | `processos()->consultar()` |
| consultarProcedimentoIndividual | `processos()->consultarIndividual()` |
| enviarEmail | `processos()->enviarEmail()` |
| enviarProcesso | `processos()->enviar()` |
| excluirProcesso | `processos()->excluir()` |
| gerarProcedimento | `processos()->gerar()` |
| relacionarProcesso / removerRelacionamentoProcesso | `processos()->relacionar()` / `removerRelacionamento()` |
| sobrestarProcesso / removerSobrestamentoProcesso | `processos()->sobrestar()` / `removerSobrestamento()` |
| registrarAnotacao | `processos()->registrarAnotacao()` |
| definirMarcador | `processos()->definirMarcador()` |
| definirControlePrazo / concluirControlePrazo / removerControlePrazo | `processos()->definirControlePrazo()` / `concluirControlePrazo()` / `removerControlePrazo()` |
| lancarAndamento | `andamentos()->lancar()` / `atualizar()` |
| listarAndamentos | `andamentos()->listar()` |
| listarAndamentosMarcadores | `andamentos()->listarMarcadores()` |
| incluirDocumento | `documentos()->incluir()` |
| consultarDocumento | `documentos()->consultar()` |
| cancelarDocumento / excluirDocumento | `documentos()->cancelar()` / `excluir()` |
| gerarBloco / consultarBloco | `blocos()->gerar()` / `consultar()` |
| concluirBloco / reabrirBloco / excluirBloco | `blocos()->concluir()` / `reabrir()` / `excluir()` |
| disponibilizarBloco / cancelarDisponibilizacaoBloco / devolverBloco | `blocos()->disponibilizar()` / `cancelarDisponibilizacao()` / `devolver()` |
| incluirDocumentoBloco / retirarDocumentoBloco | `blocos()->incluirDocumento()` / `retirarDocumento()` |
| incluirProcessoBloco / retirarProcessoBloco | `blocos()->incluirProcesso()` / `retirarProcesso()` |
| listarContatos / atualizarContatos | `contatos()->listar()` / `todos()` / `atualizar()` |
| registrarOuvidoria / listarTiposProcedimentoOuvidoria | `ouvidoria()->registrar()` / `tiposProcedimento()` |
| listarUnidades | `catalogos()->unidades()` |
| listarSeries | `catalogos()->series()` |
| listarTiposProcedimento | `catalogos()->tiposProcedimento()` |
| listarTiposConferencia / listarTiposPrioridade | `catalogos()->tiposConferencia()` / `tiposPrioridade()` |
| listarHipotesesLegais | `catalogos()->hipotesesLegais()` |
| listarMarcadoresUnidade | `catalogos()->marcadoresUnidade()` |
| listarPaises / listarEstados / listarCidades | `catalogos()->paises()` / `estados()` / `cidades()` |
| listarCargos / listarFeriados | `catalogos()->cargos()` / `feriados()` |
| listarExtensoesPermitidas / listarUsuarios | `catalogos()->extensoesPermitidas()` / `usuarios()` |

### SIP

| Operação | Método |
|---|---|
| listarPermissao | `Sip::listarPermissao()` |
| replicarPermissao | `Sip::replicarPermissao()` |
| replicarUsuario | `Sip::replicarUsuario()` |

## Desenvolvimento

```bash
composer test              # testes unitários e da camada Laravel
composer analyse           # PHPStan nível 8
composer format            # Laravel Pint
```

**Validação na homologação.** Copie `.env.homologacao.example` para `.env.homologacao` (ignorado pelo Git) e preencha. Depois:

```bash
composer wsdl:baixar        # salva tests/Fixtures/sei-5.0.wsdl e sip.wsdl
composer test               # o teste de contrato confere operação por operação com a WSDL real
composer test:integration   # cria e conclui processos reais na homologação
```

No teste de integração, abra o documento gerado no editor do SEI e confira os acentos: se aparecerem trocados, use `SEI_CONTEUDO_CHARSET=ISO-8859-1`.

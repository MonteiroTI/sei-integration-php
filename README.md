# sei-integration

Cliente PHP para os WebServices SOAP do **SEI** e do **SIP** (versão 5.0), com integração opcional ao **Laravel**.

- Cobre todas as **66 operações do SEI** e as **3 do SIP** do manual *SEI WebServices v5.0*.
- Tem DTOs tipados (`readonly`), enums e validação local antes de cada chamada.
- O núcleo não depende de framework. A camada Laravel oferece ServiceProvider, facades `Sei`/`Sip`, várias conexões nomeadas, cache de catálogos, logs com a chave mascarada, eventos, comandos Artisan e `Sei::fake()` para testes.
- Envia arquivos grandes em partes (`adicionarArquivo` + `adicionarConteudoArquivo`), sem precisar de MTOM.

> O módulo [mod-sei-protocolo-integrado](https://github.com/pengovbr/mod-sei-protocolo-integrado) roda dentro do SEI e só envia metadados ao Protocolo Integrado. Ele não expõe endpoints para sistemas externos, por isso não faz parte desta biblioteca. Processos criados por ela chegam ao PI pelo próprio SEI, se o órgão usar o módulo.

## Requisitos

- PHP 8.2 ou superior, com `ext-soap` e `ext-mbstring`
- Laravel 11 ou 12 (opcional)

## Instalação

```bash
composer require monteiroti/sei-integration
```

Se o pacote não estiver no Packagist, adicione o repositório Git em `repositories` no `composer.json` da aplicação.

No Laravel, o provider é registrado sozinho. Para publicar a configuração:

```bash
php artisan vendor:publish --tag=sei-config
```

## 1. Cadastro no SEI

O manual descreve o cadastro no capítulo 1. Em resumo:

1. Em **Administração › Sistemas**, cadastre o sistema cliente. A *sigla* informada vira o `SiglaSistema`.
2. No ícone **Serviços** do sistema, cadastre um serviço e marque **Autenticação: Chave de Acesso**.
   - A autenticação por *Endereço* será removida em versões futuras do SEI.
   - Marque **Gerar Links de Acesso Externos** se os usuários do seu sistema não tiverem login no SEI.
3. No ícone **Operações** do serviço, libere as operações que o sistema vai usar. Você pode restringi-las por unidade, tipo de processo e tipo de documento.
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

Para validar a conexão:

```bash
php artisan sei:ping                  # WSDL, compatibilidade das operações e credenciais
php artisan sei:ping homologacao
php artisan sei:ping --sip
php artisan sei:catalogo unidades     # IDs para preencher sua configuração
php artisan sei:catalogo tipos-processo --unidade=110000001
php artisan sei:catalogo series --json
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
| `AccessDeniedException` | Sistema, serviço, chave, operação, unidade ou tipo não liberados |
| `ValidationException` | Erros acumulados de `atualizarContatos`, `replicarUsuario` e `replicarPermissao` (`->errors()`) |
| `FaultException` | Demais erros de negócio do SEI (`->messages()`, `->faultCode`) |
| `InvalidArgumentException` | Dados inválidos detectados antes da chamada |

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

## 6. Logs e eventos

- `SEI_LOG=true` registra cada chamada, com duração e argumentos, no canal `SEI_LOG_CHANNEL`. A chave de acesso aparece como `***` e conteúdos Base64 vêm resumidos.
- Os eventos `SeiRequestSending`, `SeiResponseReceived` e `SeiRequestFailed` (em `MonteiroTi\Sei\Laravel\Events`) servem para métricas e auditoria.

## 7. Decisões e pontos a validar

- **Encoding.** Por padrão a aplicação trabalha em UTF-8 e o `\SoapClient` envia UTF-8. O manual usa `'encoding' => 'ISO-8859-1'` porque o próprio SEI é ISO-8859-1 internamente. Num app UTF-8 isso causaria conversão dupla, por isso essa opção só existe como configuração (`encoding`).
- **Conteúdo de documentos gerados.** O exemplo do manual (`utf8_encode`) indica HTML em UTF-8 dentro do Base64. Se os acentos aparecerem trocados no editor do SEI, use `SEI_CONTEUDO_CHARSET=ISO-8859-1`. **Confirme na homologação** (há um teste de integração para isso).
- **Ordem dos parâmetros.** A WSDL é RPC: os parâmetros são posicionais. A ordem segue o manual 5.0 e foi conferida com o cliente público `rsei`. Rode `php artisan sei:ping` contra o seu SEI, ou salve a WSDL em `tests/Fixtures/sei-5.0.wsdl`, para conferir operação por operação.
- **IDs.** São sempre `string`, porque passam de 32 bits. Flags `S`/`N` viram `bool` e datas viram `DateTimeImmutable` (fuso `America/Sao_Paulo`).
- **MTOM.** Não é suportado (o `\SoapClient` nativo não tem MTOM). Use `enviarArquivo()`.

## 8. Mapa operação → método

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

**Contrato com a WSDL real.** Salve as WSDLs da homologação em `tests/Fixtures/` e o teste de contrato passa a rodar:

```bash
curl -o tests/Fixtures/sei-5.0.wsdl "https://sei-hml.orgao.gov.br/sei/controlador_ws.php?servico=sei"
```

**Integração com uma homologação.** Estes testes criam e concluem processos reais:

```bash
SEI_TEST_WSDL=... SEI_TEST_SIGLA=... SEI_TEST_CHAVE=... SEI_TEST_UNIDADE=... \
SEI_TEST_TIPO_PROCESSO=... SEI_TEST_SERIE_GERADO=... SEI_TEST_SERIE_EXTERNO=... \
composer test:integration
```

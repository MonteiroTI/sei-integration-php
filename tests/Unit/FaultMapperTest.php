<?php

declare(strict_types=1);

use MonteiroTi\Sei\Core\Exceptions\AccessDeniedException;
use MonteiroTi\Sei\Core\Exceptions\ConnectionException;
use MonteiroTi\Sei\Core\Exceptions\FaultException;
use MonteiroTi\Sei\Core\Exceptions\FaultMapper;
use MonteiroTi\Sei\Core\Exceptions\ValidationException;
use MonteiroTi\Sei\Core\Support\Redactor;
use MonteiroTi\Sei\Operations\SeiOperations;

it('classifica faults', function (string $code, string $message, string $operation, string $class) {
    expect(FaultMapper::map(new SoapFault($code, $message), $operation))->toBeInstanceOf($class);
})->with([
    'http' => ['HTTP', 'Could not connect to host', 'listarUnidades', ConnectionException::class],
    'acesso' => ['Server', 'Acesso negado ao serviço [X]', 'gerarProcedimento', AccessDeniedException::class],
    'operação' => ['Server', 'Operação não está liberada para o serviço', 'gerarProcedimento', AccessDeniedException::class],
    'contatos' => ['Server', "Contato A: CPF inválido.\nContato B: nome obrigatório.", 'atualizarContatos', ValidationException::class],
    'negócio' => ['Server', 'Processo 12.1.000000077-4 não encontrado.', 'concluirProcesso', FaultException::class],
]);

it('separa mensagens acumuladas', function () {
    $e = FaultMapper::map(new SoapFault('Server', "Erro 1\n\nErro 2\r\n"), 'replicarUsuario');

    expect($e)->toBeInstanceOf(ValidationException::class)
        ->and($e->errors())->toBe(['Erro 1', 'Erro 2'])
        ->and($e->operation)->toBe('replicarUsuario');
});

it('mascara credenciais e resume conteúdos longos para log', function () {
    $redacted = Redactor::redact([
        'IdentificacaoServico' => 'segredo',
        'Documento' => ['Conteudo' => str_repeat('A', 1000), 'Nome' => 'x'],
    ]);

    expect($redacted['IdentificacaoServico'])->toBe('***')
        ->and($redacted['Documento']['Conteudo'])->toStartWith('[1000 bytes]')
        ->and($redacted['Documento']['Nome'])->toBe('x');
});

it('indica se a requisição pode ter chegado ao SEI', function (string $code, string $message, bool $enviada) {
    $e = FaultMapper::map(new SoapFault($code, $message), 'gerarProcedimento');

    expect($e)->toBeInstanceOf(ConnectionException::class)
        ->and($e->possivelmenteEnviada)->toBe($enviada);
})->with([
    'host recusou' => ['HTTP', 'Could not connect to host', false],
    'dns' => ['HTTP', 'Could not resolve host: sei.test', false],
    'wsdl' => ['WSDL', 'SOAP-ERROR: Parsing WSDL', false],
    'timeout lendo resposta' => ['HTTP', 'Error Fetching http headers', true],
    'reset' => ['HTTP', 'Connection reset by peer', true],
    'erro 500 do servidor' => ['HTTP', 'Internal Server Error', true],
]);

it('classifica operações idempotentes', function () {
    expect(SeiOperations::idempotente('gerarProcedimento'))->toBeFalse()
        ->and(SeiOperations::idempotente('consultarProcedimento'))->toBeTrue()
        ->and(array_diff(SeiOperations::NAO_IDEMPOTENTES, SeiOperations::all()))->toBe([]);
});

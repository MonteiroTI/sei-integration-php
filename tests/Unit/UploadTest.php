<?php

declare(strict_types=1);

use MonteiroTi\Sei\Core\Exceptions\InvalidArgumentException;

it('envia arquivos em partes com tamanho e MD5 do conteúdo total', function () {
    $sei = fakeSei($t, chunk: 4);
    $t->respond('adicionarArquivo', '777')->respond('adicionarConteudoArquivo', '777');

    $path = tempnam(sys_get_temp_dir(), 'sei');
    file_put_contents($path, 'abcdefghij'); // 10 bytes -> 4 + 4 + 2

    try {
        $id = $sei->documentos()->enviarArquivo($path, 'documento.pdf');
    } finally {
        unlink($path);
    }

    $inicio = $t->lastArguments('adicionarArquivo');
    $partes = array_map(fn ($c) => base64_decode($c['arguments']['Conteudo']), $t->calls('adicionarConteudoArquivo'));

    expect($id)->toBe('777')
        ->and($inicio['Nome'])->toBe('documento.pdf')
        ->and($inicio['Tamanho'])->toBe(10)
        ->and($inicio['Hash'])->toBe(md5('abcdefghij'))
        ->and(base64_decode($inicio['Conteudo']))->toBe('abcd')
        ->and($partes)->toBe(['efgh', 'ij'])
        ->and($t->calls('adicionarConteudoArquivo')[0]['arguments']['IdArquivo'])->toBe('777');
});

it('envia arquivo pequeno em uma única chamada e aceita streams', function () {
    $sei = fakeSei($t);
    $t->respond('adicionarArquivo', '1');

    $stream = fopen('php://memory', 'w+b');
    fwrite($stream, 'conteudo');

    expect($sei->documentos()->enviarArquivo($stream, 'a.txt'))->toBe('1')
        ->and($t->calls('adicionarConteudoArquivo'))->toBe([])
        ->and(is_resource($stream))->toBeTrue();
});

it('recusa arquivo vazio, inexistente ou stream sem nome', function () {
    $sei = fakeSei();
    $empty = fopen('php://memory', 'w+b');

    expect(fn () => $sei->documentos()->enviarArquivo('/nao/existe.pdf'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $sei->documentos()->enviarArquivo($empty, 'x'))->toThrow(InvalidArgumentException::class, 'vazio')
        ->and(fn () => $sei->documentos()->enviarArquivo($empty))->toThrow(InvalidArgumentException::class, 'nome');
});

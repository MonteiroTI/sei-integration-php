<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;

function guiaClaude(): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/resources/claude/skills/sei-integration/SKILL.md');
}

it('publica a skill no diretório .claude do sistema', function () {
    $paths = ServiceProvider::pathsToPublish(null, 'sei-claude');

    expect($paths)->toHaveCount(1)
        ->and(reset($paths))->toEndWith('.claude/skills/sei-integration/SKILL.md')
        ->and(is_file((string) array_key_first($paths)))->toBeTrue();
});

it('tem o frontmatter de skill', function () {
    expect(guiaClaude())->toStartWith("---\nname: sei-integration\ndescription: ");
});

it('menciona todos os comandos sei:* registrados', function () {
    $comandos = array_filter(array_keys(Artisan::all()), fn (string $c) => str_starts_with($c, 'sei:'));

    expect($comandos)->not->toBeEmpty();
    foreach ($comandos as $comando) {
        expect(guiaClaude())->toContain($comando);
    }
});

it('menciona todos os eventos de sincronização', function () {
    foreach (glob(dirname(__DIR__, 2).'/src/Laravel/Events/Sincronizacao/*.php') as $arquivo) {
        expect(guiaClaude())->toContain(basename($arquivo, '.php'));
    }
});

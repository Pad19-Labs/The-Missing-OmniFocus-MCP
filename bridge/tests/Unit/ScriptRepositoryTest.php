<?php

use App\OmniFocus\Exceptions\ScriptException;
use App\OmniFocus\ScriptRepository;

function repository(): ScriptRepository
{
    return new ScriptRepository(dirname(__DIR__, 2).'/resources/omnijs');
}

it('prepends arguments as a JSON ARGS constant', function () {
    $script = repository()->compose('list_inbox', ['limit' => 25, 'offset' => 5]);

    expect($script)->toStartWith('const ARGS = {"limit":25,"offset":5};');
});

it('encodes empty arguments as an empty object, not an array', function () {
    $script = repository()->compose('overview');

    expect($script)->toStartWith('const ARGS = {};');
});

it('keeps hostile argument values inside the JSON string (no JS injection)', function () {
    $script = repository()->compose('search', ['query' => '"; deleteObject(library); "']);

    // Exact equality with json_encode proves the value went through JSON
    // escaping — the quote arrives as \" and can never terminate the literal.
    $lines = explode("\n", $script);
    expect($lines[0])->toBe('const ARGS = '.json_encode(['query' => '"; deleteObject(library); "']).';')
        ->and($lines[0])->toContain('\\"; deleteObject');
});

it('escapes unicode line separators that would break JS string literals', function () {
    $script = repository()->compose('search', ['query' => "line\u{2028}separator"]);

    expect(explode("\n", $script)[0])->toContain('\\u2028');
});

it('includes the shared library and wraps the template in a guarded IIFE', function () {
    $script = repository()->compose('list_inbox', ['limit' => 10]);

    expect($script)
        ->toContain('function serializeTask(')
        ->toContain('function taskStatusName(')
        ->toContain('(() => {')
        ->toContain('} catch (e) {')
        ->toContain('inbox.slice');
});

it('throws for an unknown template name', function () {
    repository()->compose('does_not_exist');
})->throws(ScriptException::class);

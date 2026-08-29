<?php

namespace App\OmniFocus;

use App\OmniFocus\Exceptions\ScriptException;

class ScriptRepository
{
    public function __construct(private readonly string $basePath) {}

    /**
     * Build a complete omniJS script: JSON-encoded ARGS (the injection barrier —
     * values never touch the JS source as code), the shared library, then the
     * template body wrapped in a guarded IIFE that reports errors as JSON.
     */
    public function compose(string $name, array $args = []): string
    {
        $argsJson = json_encode($args === [] ? new \stdClass : $args, JSON_THROW_ON_ERROR);
        $lib = $this->load('_lib');
        $template = $this->load($name);

        return <<<JS
        const ARGS = {$argsJson};
        {$lib}
        (() => {
          try {
        {$template}
          } catch (e) {
            return fail("script_error", String(e && e.message ? e.message : e));
          }
        })()
        JS;
    }

    private function load(string $name): string
    {
        $path = $this->basePath.'/'.$name.'.js';

        if (! is_file($path)) {
            throw new ScriptException("Unknown omniJS template [{$name}].");
        }

        return trim(file_get_contents($path));
    }
}

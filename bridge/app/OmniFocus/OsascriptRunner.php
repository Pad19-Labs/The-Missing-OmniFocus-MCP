<?php

namespace App\OmniFocus;

use App\OmniFocus\Contracts\OmniJsRunner;
use App\OmniFocus\Exceptions\ScriptException;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class OsascriptRunner implements OmniJsRunner
{
    /**
     * JXA wrapper: receives the omniJS source as argv so it is never
     * interpolated, and hands it to OmniFocus for evaluation.
     */
    private const JXA_WRAPPER = <<<'JS'
    function run(argv) {
        const app = Application("OmniFocus");
        return app.evaluateJavascript(argv[0]);
    }
    JS;

    public function run(string $script, int $timeoutSeconds = 30): string
    {
        // OmniFocus evaluates one script at a time; serialize access so
        // concurrent bridge calls queue instead of interleaving.
        $lock = Cache::lock('omnifocus-runner', $timeoutSeconds + 5);

        return $lock->block($timeoutSeconds + 5, function () use ($script, $timeoutSeconds): string {
            $process = new Process(
                ['osascript', '-l', 'JavaScript', '-e', self::JXA_WRAPPER, $script],
                timeout: $timeoutSeconds,
            );

            $process->run();

            if (! $process->isSuccessful()) {
                $stderr = trim($process->getErrorOutput());

                throw new ScriptException($stderr !== '' ? $stderr : 'osascript failed with no error output');
            }

            return $process->getOutput();
        });
    }
}

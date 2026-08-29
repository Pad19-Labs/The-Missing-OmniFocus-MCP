<?php

namespace Tests\Support;

use App\OmniFocus\Contracts\OmniJsRunner;
use RuntimeException;

final class FakeOmniJsRunner implements OmniJsRunner
{
    /** @var list<string> every script passed to run(), in order */
    public array $scripts = [];

    /** @var list<string> */
    private array $responses = [];

    public function queue(string $rawResponse): void
    {
        $this->responses[] = $rawResponse;
    }

    public function queueOk(mixed $data): void
    {
        $this->queue(json_encode(['ok' => true, 'data' => $data]));
    }

    public function queueError(string $code, string $message): void
    {
        $this->queue(json_encode(['ok' => false, 'error' => ['code' => $code, 'message' => $message]]));
    }

    public function run(string $script, int $timeoutSeconds = 30): string
    {
        $this->scripts[] = $script;

        return array_shift($this->responses)
            ?? throw new RuntimeException('FakeOmniJsRunner has no queued response.');
    }

    public function lastScript(): string
    {
        return end($this->scripts) ?: '';
    }
}

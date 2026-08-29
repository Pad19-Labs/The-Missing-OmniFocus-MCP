<?php

namespace App\OmniFocus\Contracts;

interface OmniJsRunner
{
    /**
     * Execute an omniJS script inside OmniFocus and return its raw string result.
     */
    public function run(string $script, int $timeoutSeconds = 30): string;
}

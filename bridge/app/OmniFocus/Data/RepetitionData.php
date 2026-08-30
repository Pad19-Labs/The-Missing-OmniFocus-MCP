<?php

namespace App\OmniFocus\Data;

final readonly class RepetitionData
{
    public function __construct(
        public string $rule,
        public string $method,
    ) {}

    public static function fromArray(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }

        return new self(rule: $data['rule'], method: $data['method']);
    }
}

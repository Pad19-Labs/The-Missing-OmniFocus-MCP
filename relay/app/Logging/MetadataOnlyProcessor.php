<?php

namespace App\Logging;

use Illuminate\Http\Request;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Throwable;

/**
 * The relay is a zero-persistence pass-through: MCP payloads are the user's
 * OmniFocus data and must never reach a log line. Laravel's exception context
 * callbacks can only ADD keys, so scrubbing is enforced here instead — as a
 * Monolog processor every channel shares. That covers exception reports AND
 * any Log::* call made anywhere in the app, which is the only way this policy
 * can be enforced rather than merely intended.
 *
 * Three passes:
 *  1. Replace any context value that IS the request payload with a redaction.
 *  2. Recursively drop keys that carry bodies, credentials, or network
 *     identifiers.
 *  3. Strip the current request's own body/query/header values wherever they
 *     appear as substrings, so a message interpolating them still cannot leak.
 */
class MetadataOnlyProcessor implements ProcessorInterface
{
    public const REDACTED = '[redacted]';

    /**
     * Matched case-insensitively against context keys.
     */
    private const FORBIDDEN_KEYS = [
        'arguments', 'authorization', 'body', 'content', 'cookie', 'cookies',
        'credentials', 'device_secret', 'headers', 'input', 'ip', 'ip_address',
        'jsonrpc', 'params', 'parameters', 'password', 'payload', 'post',
        'query', 'remote_addr', 'request', 'response', 'result', 'secret',
        'server', 'session', 'token', 'user_agent',
    ];

    private const MAX_DEPTH = 6;

    public function __invoke(LogRecord $record): LogRecord
    {
        $secrets = $this->requestSecrets();

        return $record
            ->with(message: $this->scrubString($record->message, $secrets))
            ->with(context: $this->scrubArray($record->context, $secrets))
            ->with(extra: $this->scrubArray($record->extra, $secrets));
    }

    /**
     * Every value the current request carried, so none of them can survive in
     * a log line regardless of which key or message they were placed under.
     *
     * @return list<string>
     */
    private function requestSecrets(): array
    {
        if (! app()->bound('request')) {
            return [];
        }

        try {
            $request = app('request');
        } catch (Throwable) {
            return [];
        }

        if (! $request instanceof Request) {
            return [];
        }

        $values = [];

        try {
            $this->collectScalars($request->all(), $values);
            $this->collectScalars($request->query->all(), $values);
            $values[] = $request->getContent();
            $values[] = $request->bearerToken();
            $values[] = $request->getQueryString();
        } catch (Throwable) {
            // A malformed request must not stop the scrub; the key-based pass
            // below still applies.
        }

        return array_values(array_filter(
            array_unique(array_filter($values, 'is_string')),
            // One- and two-character values would redact half the alphabet out
            // of every message for no privacy gain.
            fn (string $value) => mb_strlen($value) >= 3,
        ));
    }

    /**
     * @param  list<string|null>  $into
     */
    private function collectScalars(array $values, array &$into, int $depth = 0): void
    {
        if ($depth > self::MAX_DEPTH) {
            return;
        }

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $this->collectScalars($value, $into, $depth + 1);

                continue;
            }

            if (is_string($key)) {
                $into[] = $key;
            }

            if (is_string($value) || is_int($value) || is_float($value)) {
                $into[] = (string) $value;
            }
        }
    }

    /**
     * @param  list<string>  $secrets
     */
    private function scrubArray(array $context, array $secrets, int $depth = 0): array
    {
        if ($depth > self::MAX_DEPTH) {
            return [self::REDACTED];
        }

        $scrubbed = [];

        foreach ($context as $key => $value) {
            if (is_string($key) && $this->isForbiddenKey($key)) {
                $scrubbed[$key] = self::REDACTED;

                continue;
            }

            $scrubbed[$key] = match (true) {
                is_array($value) => $this->scrubArray($value, $secrets, $depth + 1),
                is_string($value) => $this->scrubString($value, $secrets),
                // Exception objects are kept: the formatter renders only the
                // class, message, file, line, and trace — never the request.
                $value instanceof Throwable, is_scalar($value), is_null($value) => $value,
                default => self::REDACTED,
            };
        }

        return $scrubbed;
    }

    private function isForbiddenKey(string $key): bool
    {
        return in_array(mb_strtolower($key), self::FORBIDDEN_KEYS, true);
    }

    /**
     * @param  list<string>  $secrets
     */
    private function scrubString(string $value, array $secrets): string
    {
        foreach ($secrets as $secret) {
            if ($secret !== '' && str_contains($value, $secret)) {
                $value = str_replace($secret, self::REDACTED, $value);
            }
        }

        return $value;
    }
}

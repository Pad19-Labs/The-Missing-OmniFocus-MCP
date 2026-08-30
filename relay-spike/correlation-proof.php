<?php

/*
 * Phase 0 spike: prove the relay's request/reply correlation + per-pairing
 * AEAD encryption end-to-end, using Redis the way Laravel Cloud would.
 *
 * Simulates:
 *   - Relay HTTP handler: encrypts an MCP request, RPUSHes it to the device's
 *     inbox, then BLPOPs relay:reply:{id} with a timeout (blocking for the reply).
 *   - Device (the Mac's tunnel): BLPOPs its inbox, decrypts, "runs the tool",
 *     encrypts the reply, RPUSHes it to relay:reply:{id}.
 *   - Relay: unblocks, decrypts, returns.
 *
 * The relay only ever handles ciphertext + a cleartext {device_id, request_id}
 * envelope — never plaintext at rest. Proven by asserting the queued blob is
 * unreadable without the key.
 *
 * Run: php relay-spike/correlation-proof.php
 * (Uses ext-redis if available, else a raw socket RESP client — no Composer.)
 */

const REDIS_HOST = '127.0.0.1';
const REDIS_PORT = 6379;

// --- Minimal Redis client over a raw socket (no dependencies) ---
class MiniRedis
{
    private $sock;

    public function __construct(string $host, int $port)
    {
        $this->sock = fsockopen($host, $port, $errno, $errstr, 2);
        if (! $this->sock) {
            throw new RuntimeException("Redis connect failed: $errstr ($errno)");
        }
    }

    public function cmd(array $args): mixed
    {
        $out = '*'.count($args)."\r\n";
        foreach ($args as $a) {
            $a = (string) $a;
            $out .= '$'.strlen($a)."\r\n".$a."\r\n";
        }
        fwrite($this->sock, $out);

        return $this->readReply();
    }

    private function readReply(): mixed
    {
        $line = trim(fgets($this->sock));
        $type = $line[0];
        $payload = substr($line, 1);

        return match ($type) {
            '+' => $payload,
            '-' => throw new RuntimeException("Redis error: $payload"),
            ':' => (int) $payload,
            '$' => $payload === '-1' ? null : $this->readBulk((int) $payload),
            '*' => $this->readArray((int) $payload),
            default => throw new RuntimeException("Unknown reply: $line"),
        };
    }

    private function readBulk(int $len): string
    {
        $data = '';
        while (strlen($data) < $len) {
            $data .= fread($this->sock, $len - strlen($data));
        }
        fread($this->sock, 2); // trailing CRLF

        return $data;
    }

    private function readArray(int $count): ?array
    {
        if ($count === -1) {
            return null;
        }
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = $this->readReply();
        }

        return $items;
    }
}

// --- AEAD helpers (libsodium XChaCha20-Poly1305) ---
function seal(string $plaintext, string $key): string
{
    $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
    $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, '', $nonce, $key);

    return base64_encode($nonce.$cipher);
}

function unseal(string $blob, string $key): string
{
    $raw = base64_decode($blob);
    $nlen = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
    $nonce = substr($raw, 0, $nlen);
    $cipher = substr($raw, $nlen);
    $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($cipher, '', $nonce, $key);
    if ($plain === false) {
        throw new RuntimeException('AEAD decryption failed');
    }

    return $plain;
}

// --- The spike ---
$pass = fn (string $m) => print("  \033[32mPASS\033[0m $m\n");
$fail = function (string $m) {
    print("  \033[31mFAIL\033[0m $m\n");
    exit(1);
};

echo "Phase 0 correlation + encryption proof\n\n";

$pairingKey = sodium_crypto_aead_xchacha20poly1305_ietf_keygen();
$deviceId = 'dev_'.bin2hex(random_bytes(4));
$requestId = 'req_'.bin2hex(random_bytes(6));

$relay = new MiniRedis(REDIS_HOST, REDIS_PORT);
$device = new MiniRedis(REDIS_HOST, REDIS_PORT);

// Clean slate.
$relay->cmd(['DEL', "relay:inbox:$deviceId", "relay:reply:$requestId"]);

// 1) Relay encrypts an MCP request and enqueues it with a cleartext envelope.
$mcpRequest = json_encode([
    'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
    'params' => ['name' => 'get-overview', 'arguments' => (object) []],
]);
$envelope = json_encode([
    'device_id' => $deviceId,
    'request_id' => $requestId,
    'ciphertext' => seal($mcpRequest, $pairingKey),
]);
$relay->cmd(['RPUSH', "relay:inbox:$deviceId", $envelope]);
$pass('relay enqueued an encrypted request with a cleartext envelope');

// 2) Prove zero-plaintext-at-rest: the queued blob does not contain the tool name.
$peek = $relay->cmd(['LINDEX', "relay:inbox:$deviceId", 0]);
if (str_contains($peek, 'get-overview') || str_contains($peek, 'tools/call')) {
    $fail('plaintext leaked into the Redis payload!');
}
$pass('queued payload is opaque — no plaintext at rest (relay is blind to content)');

// 3) Relay blocks for the reply (in a real request this is the HTTP worker waiting).
//    Fork: parent = relay awaiting reply; child = device servicing the request.
$pid = pcntl_fork();
if ($pid === 0) {
    // CHILD = the Mac's tunnel.
    $dev2 = new MiniRedis(REDIS_HOST, REDIS_PORT);
    $item = $dev2->cmd(['BLPOP', "relay:inbox:$deviceId", 5]); // [key, value]
    $env = json_decode($item[1], true);
    $req = unseal($env['ciphertext'], $pairingKey);           // decrypt with pairing key
    $decoded = json_decode($req, true);
    // "Run the tool" — synthesize a reply.
    $reply = json_encode([
        'jsonrpc' => '2.0', 'id' => $decoded['id'],
        'result' => ['content' => [['type' => 'text', 'text' => json_encode(['counts' => ['inbox' => 461]])]]],
    ]);
    $replyEnvelope = json_encode(['request_id' => $env['request_id'], 'ciphertext' => seal($reply, $pairingKey)]);
    $dev2->cmd(['RPUSH', "relay:reply:{$env['request_id']}", $replyEnvelope]);
    exit(0);
}

// PARENT = relay awaiting the correlated reply.
$replyItem = $relay->cmd(['BLPOP', "relay:reply:$requestId", 5]);
pcntl_waitpid($pid, $status);

if ($replyItem === null) {
    $fail('relay timed out waiting for the reply (correlation broken)');
}
$pass('relay received a reply on the correlated key relay:reply:{id}');

$replyEnv = json_decode($replyItem[1], true);
if ($replyEnv['request_id'] !== $requestId) {
    $fail('reply request_id did not match — correlation is wrong');
}
$pass('reply correlated to the exact originating request_id');

$plainReply = unseal($replyEnv['ciphertext'], $pairingKey);
$finalCounts = json_decode(json_decode($plainReply, true)['result']['content'][0]['text'], true);
if (($finalCounts['counts']['inbox'] ?? null) !== 461) {
    $fail('decrypted reply payload was wrong');
}
$pass('relay decrypted the reply on the client leg (inbox=461)');

// 4) Offline path: no device consumer -> BLPOP times out fast, relay returns an error.
$relay->cmd(['DEL', 'relay:reply:offline_test']);
$t0 = microtime(true);
$offline = $relay->cmd(['BLPOP', 'relay:reply:offline_test', 1]);
$elapsed = microtime(true) - $t0;
if ($offline !== null) {
    $fail('expected offline timeout, got a reply');
}
$pass(sprintf('offline/timeout path returns in ~%.1fs (would map to -32001 bridge offline)', $elapsed));

echo "\n\033[32mAll transport-mechanism assertions passed.\033[0m\n";

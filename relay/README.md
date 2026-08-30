# OmniFocus MCP Relay — Phase 1

The public half of [The Missing OmniFocus MCP](../README.md). The relay is the
app both sides dial *outbound* to: a Mac running the bridge in tunnel mode, and
a cloud MCP client (claude.ai, a phone, a Linux box) that cannot otherwise reach
a machine behind a home router.

This directory is **Phase 1 only**: accounts, pairing, tokens, revocation. There
is no tunnel and no MCP endpoint here yet — see "Not built yet" below.

## What Phase 1 contains

**Access requests and allowlist.** No open signup. `POST /api/access-requests`
records an email as `pending`; an admin approves it from the CLI, which is what
creates the `User`. The endpoint always answers `pending` regardless of the
stored state, so it cannot be used to probe which addresses are already approved.

**Device pairing.** An approved user gets a single-use pairing code, good for 15
minutes, drawn from an alphabet with the characters people misread removed
(no `0/O`, `1/I/L`, `U/V`). `POST /api/pair {code, device_name}` redeems it and
returns `{device_id, device_secret}` exactly once. Redemption is rate limited
per code-prefix and globally.

**Bearer tokens.** `relay:token` mints a per-user API token, displayed once. The
`relay.token` middleware (`EnsureRelayToken`) authenticates
`Authorization: Bearer` and fails closed: no header, an unknown token, a revoked
token, or a token whose user is gone all return an identical 401.

**Revocation.** Devices and tokens carry `revoked_at`, checked inside the lookup
itself so a future caller cannot forget it.

**Metadata-only logging, enforced.** See "Privacy" below.

## Commands

```sh
php artisan relay:approve <email>          # approve a request, create the user
php artisan relay:deny <email>             # deny a request
php artisan relay:pair <email>             # mint a pairing code (shown once)
php artisan relay:token <email>            # mint a bearer token (shown once)
php artisan relay:revoke-device <uuid>     # revoke a paired device
php artisan relay:revoke-token <id>        # revoke a bearer token
```

Every secret is displayed once and stored only as a keyed hash; none can be
recovered from the database.

## Privacy

The project's rule is no PII, and Phase 1 enforces it rather than documenting it:

- **No IP addresses, anywhere.** No column stores one, no code reads one, and
  rate limits are keyed on a hash of the credential being presented rather than
  on the caller. The framework's `sessions` table — whose default schema has an
  `ip_address` column — is deleted, and the session driver is `array`.
- **No request bodies in logs.** `MetadataOnlyProcessor` is attached as a tap to
  *every* configured log channel (in `AppServiceProvider`, so a channel added
  later cannot bypass it). It redacts body-bearing context keys, and strips the
  current request's own values out of messages and context wherever they appear.
  Exception messages and stack traces survive, so failures stay diagnosable.
- **Nothing recoverable at rest.** Pairing codes, device secrets, and API tokens
  are stored as keyed SHA-256 HMACs.

Tests in `tests/Feature/LoggingPolicyTest.php` and
`tests/Feature/NoPlaintextAtRestTest.php` assert each of these, including one
that writes through the real, unswapped log channel.

### Why HMAC rather than Argon2id

Argon2id exists to make brute force expensive against *low-entropy* secrets. The
long-lived secrets here (device secrets, API tokens) are machine-generated with
256 bits of entropy — nothing to brute force. Pairing codes are shorter (~49
bits, chosen for typability) and lean instead on being single-use, 15-minute,
and rate-limited; offline brute force also requires `APP_KEY` and must win
within the code's lifetime. A deterministic keyed hash buys an O(1)
indexed lookup of a presented credential. Argon2id salts each row, which forces a
full table scan with one expensive verify per row: both a timing oracle (auth
latency would scale with row count and match position) and a cheap CPU DoS.
Keying with `APP_KEY` means a stolen database alone still cannot confirm a guess.
The reasoning is recorded in `app/Support/SecretHasher.php`.

## Layout

```
app/
  Console/Commands/     relay:approve, deny, pair, token, revoke-device, revoke-token
  Enums/                AccessRequestStatus
  Http/Controllers/     AccessRequestController, PairingController
  Http/Middleware/      EnsureRelayToken
  Http/Requests/        StoreAccessRequestRequest, RedeemPairingCodeRequest
  Logging/              MetadataOnlyProcessor, ScrubRequestData
  Models/               User, AccessRequest, Device, PairingCode, ApiToken
  Support/              SecretHasher
```

## Running it

```sh
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
./vendor/bin/pest          # test suite
./vendor/bin/pint          # formatting
```

SQLite for development; tests run in memory. `APP_KEY` is fixed in `phpunit.xml`
so CI needs no `.env`.

## Not built yet — deliberately

Phase 1 stops at the account boundary. Still to come:

- **Phase 2 — the tunnel.** The Mac's long-poll loop (`GET /tunnel/next` +
  `POST /tunnel/reply`), Redis `BLPOP`/`RPUSH` request/reply correlation,
  X25519 per-connection key agreement, AEAD frames, presence keys, and killing
  live tunnels on revocation. The `revoked_at` checks Phase 2 needs already
  exist. Note that Phase 0's WebSocket plan was **withdrawn**: FrankenPHP cannot
  accept WebSocket connections from PHP, so v1 is HTTPS long-poll
  (`relay-spike/FINDINGS.md`, corrections section).
- **Phase 3 — the MCP endpoint.** Public streamable-HTTP MCP, `tools/list` from
  a static manifest, an immediate `-32001` when no device is online, per-token
  rate limits and per-user concurrency caps.
- **Phase 4 — packaging.** Laravel Cloud deploy config, pairing docs, the
  self-host guide.

Also deliberately absent: any admin UI (approval is an artisan command), any
OmniFocus logic, email delivery on approval, and OAuth 2.1 (bearer tokens are
the v1 posture).

# Phase 0 — Cloud Relay transport spike

## Laravel Cloud hosting the WS gateway (the "won't work as stated" risk)

**Verdict: workable, with one routing caveat.**

- Worker clusters explicitly "can run a custom set of queue workers OR **custom
  background processes**" and can be set **always-awake** — so a long-lived
  `artisan` daemon (our WS gateway) is a supported process type. ✅
- **Managed Redis (Valkey)** available → backs BLPOP/RPUSH request/reply. ✅
- **Octane + FrankenPHP** available on the App cluster → solves the blocking-
  worker risk (async, boots once, in-memory). ✅

### Caveat — inbound WebSocket routing
- Inbound traffic hits the **App cluster on port 8080**; **workers do NOT serve
  web traffic.** So a gateway on a worker cluster can't directly *accept* the
  Mac's inbound WS connection.
- Options:
  1. **Run the gateway on the App cluster** as an Octane/FrankenPHP server that
     ALSO speaks WebSocket (FrankenPHP supports it), co-located with the MCP
     HTTP endpoint — simplest routing, one cluster sees both legs.
  2. **Managed Reverb** on Laravel Cloud for the socket accept layer, with the
     App handling MCP HTTP; but Reverb is pub/sub (see below) — only viable if
     used purely as the transport pipe, correlation still via Redis.
  3. **Gateway on a separate tiny host** (Fly.io) that accepts the WS and shares
     the managed Redis — clean separation, one more moving part.
- **Recommendation for v1: option 1** (FrankenPHP on the App cluster handling
  both the MCP HTTP endpoint and the WS accept, Redis for correlation). Keeps
  everything on Laravel Cloud, one deploy, no extra host.

## Redis correlation mechanism — PROVE LOCALLY (this spike)

**Verdict: PROVEN.** `correlation-proof.php` demonstrates the full round-trip:
- Relay RPUSHes an encrypted request + cleartext {device_id, request_id}
  envelope, then BLPOPs relay:reply:{id} (the blocking HTTP worker).
- A forked "device" BLPOPs its inbox, decrypts with the per-pairing key, runs
  the tool, encrypts the reply, RPUSHes it to the correlated reply key.
- Relay unblocks, correlation matches, decrypts on the client leg.
- **Zero plaintext at rest asserted**: the queued Redis blob contains neither
  the tool name nor "tools/call" — relay is blind to content.
- **Offline/timeout path**: BLPOP returns in ~1s when no device answers, which
  maps to an immediate JSON-RPC -32001 "bridge offline" instead of a hang.

Encryption: libsodium XChaCha20-Poly1305 AEAD (sodium ext present, no Composer
needed for the crypto). Redis via a dependency-free raw-socket RESP client.

## Conclusion
Phase 0 clears the biggest risks. The architecture holds:
- Laravel Cloud CAN host the daemon (worker cluster background process / or
  FrankenPHP on the App cluster handling both HTTP + WS accept). Recommend
  FrankenPHP-on-App for v1 (one cluster, one deploy).
- Redis BLPOP/RPUSH request/reply correlation works and is the right primitive.
- Per-pairing AEAD gives the honest "zero plaintext at rest" posture.
- Offline detection is clean and fast.
Ready to proceed to Phase 1 (relay skeleton).

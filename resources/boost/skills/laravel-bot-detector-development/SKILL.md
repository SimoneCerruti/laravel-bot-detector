---
name: laravel-bot-detector-development
description: >
  Bot detection with simonecerruti/laravel-bot-detector. Use when protecting routes from bots, reading a request's
  bot verdict, writing a reaction (challenge, custom deny response) or signal, choosing reactions per request with
  a bot policy, listening to BotDetected, or explaining a visitor's verdict.
license: MIT
metadata:
  author: Simone Cerruti
---

# Laravel Bot Detector

## Primary Goal

Protect a Laravel app from bots through the package's public API, rolled out so real visitors and real crawlers keep working: dry run first, enforcement after tuning.

## Workflow

### 1. Inspect the app

- Read `config/bot-detector.php` if published (otherwise the defaults apply: `vendor/simonecerruti/laravel-bot-detector/config/bot-detector.php`), the routes to protect, and `bootstrap/app.php`.
- Done when you know: which routes need protection, whether a proxy, load balancer or CDN sits in front of the app, and the current `dry_run.enabled` value.

### 2. Trust the proxy

- Behind any proxy, configure `$middleware->trustProxies(at: [...])` in `bootstrap/app.php` with the proxy's addresses. An untrusted proxy makes every visitor share the proxy IP: real Googlebot fails DNS verification and is blocked as `automated`, throttling pools visitors, `except_ips` and the HTTPS-only `Sec-*` signals stop working.
- Done when trusted proxies are configured, or you confirmed no proxy exists.

### 3. Protect the routes

- Add the `bot-detector` route middleware: `Route::middleware('bot-detector')->group(...)`. Use `bot-detector:log-only` for routes to observe without ever enforcing.
- Put machine-only endpoints (webhooks, health checks) in `except_paths`, trusted callers in `except_ips`.
- Done when every route to protect carries the middleware and every machine-only endpoint is excepted.

### 4. Shape the reactions

- Configure `reactions` per verdict (`good_bot`, `suspicious`, `automated`) with `Log`, `Throttle`, `Block` from `SimoneCerruti\BotDetector\Reactions`, or app reactions.
- An app reaction implements `Contracts\Reaction` (`decide(Request, Detection): Decision`); add `Contracts\RespondsToDeny` (`denyResponse(Request, Detection): Response`) for its own deny response, such as a redirect to a challenge page outside the middleware.
- Per-request choices go in one `BotDetector::usePolicy(fn (Request $request, Detection $detection, array $reactions): array => ...)` in a service provider's `boot()`. It runs for human requests too, with `$reactions === []`.
- Side effects (alerts, ban lists) go in a `BotDetected` listener that returns early when `$event->dry_run` is true.
- Done when each verdict's reactions, policy and listeners match what the user asked for.

### 5. Keep dry run on until tuned

- Leave `dry_run.enabled` true for the first deploy. Tell the user to collect about a week of `Bot detected` logs, adjust `signals` weights, `suspicious_at`, `automated_at` and the excepts, then set `dry_run.enabled` to false.
- Explain a single visitor with `php artisan bot-detector:verify {ip} "{ua}" --header="Name: value"`.
- Done when enforcement is either still in dry run, or the user confirmed tuning is finished.

### 6. Test in the app

- Feature-test protected routes with `config(['bot-detector.dry_run.enabled' => false])` and a `curl/8.5.0` User-Agent: expect `403` (or the custom reaction's response); a full browser header set passes.
- Done when each configured reaction and policy branch has a passing test.

## References

- `vendor/simonecerruti/laravel-bot-detector/README.md`: verdict rules, signals and weights, `Detection` fields, Turnstile challenge example, tuning guide, privacy, full config reference. Read it before writing a reaction, signal or policy.
- `config/bot-detector.php`: every option, each documented inline.

## Examples

Read the verdict anywhere, with or without the middleware (computed once per request):

```php
use SimoneCerruti\BotDetector\Facades\BotDetector;

if (! $request->botDetection()->isBot()) {
    PageView::record($request);
}

$verdict = BotDetector::detect($request)->verdict; // Verdict::Human, GoodBot, Suspicious, Automated
```

Welcome feed readers, never block them:

```php
BotDetector::usePolicy(fn (Request $request, Detection $detection, array $reactions): array => $request->routeIs('feed') && $detection->isBot()
    ? [Log::class]
    : $reactions);
```

Add a signal with its weight:

```php
'signals' => [
    // ...the built-in signals
    App\BotSignals\OldChrome::class => 2, // implements SimoneCerruti\BotDetector\Contracts\Signal
],
```

## Anti-patterns

- Ship with `dry_run.enabled` true; switching enforcement on is the user's call after tuning.
- Welcome search engines through `good_bots` (User-Agent regex => DNS hostname suffixes), which verifies the IP; a User-Agent check alone lets anyone pretend.
- Store the hashed or dropped IP: `Detection::toArray()` and `fingerprint->ip` hold the raw address, while the `Log` reaction already writes an HMAC unless `log.raw_ip` is on.
- Use only the public API: the facade, the request macro, `Detection`, `RequestFingerprint`, `BotDetected`, the `Reaction`, `RespondsToDeny` and `Signal` contracts, the built-in reactions and signals, the enums `Verdict` and `Decision`.

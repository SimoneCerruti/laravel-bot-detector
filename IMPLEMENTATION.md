# laravel-bot-detector: how to build it

Package `simonecerruti/laravel-bot-detector`: classify each request as human / known good bot / automated, and
decide what to do about it (flag, log, throttle, block, serve a lighter response, exclude from analytics).

Reference: `~/projects/simonecerruti.com`. There the **detection** exists and is embedded in analytics; the **post-detection
handling** does not exist yet (a detected bot is only stored as `device_type = bot` and filtered from reports).
Sections marked **NEW** are design, not extraction.

## 1. What exists in the reference

| Reference file | Role |
| --- | --- |
| `app/Support/DeviceTypeDetector.php` | UA classification via `matomo/device-detector`: self-declared bots, HTTP libraries (`client type = library`), fallback regex (`bot|crawler|spider|...`) used only when Matomo returns unknown |
| `app/Support/BotSignalDetector.php` | Header-consistency score for forged UAs: missing `Accept`, `Accept-Language`, `Accept-Encoding`, and missing `Sec-Fetch-Site` only when the UA claims Chrome/Edge/Opera/Firefox. `score()` + `isAutomated()` |
| `config/analytics.php` -> `bot_detection` | `threshold` (4) and per-signal `weights` |
| `app/Enums/DeviceType.php` | `BOT` is one of the buckets |
| `TrackVisit::touchSession()` | Score is re-evaluated each request, the session keeps the running **maximum**; escalation is one-way (never cleared) |
| migration `add_bot_score_to_visit_sessions_table` | `bot_score` unsigned tinyint |

## 2. Package scope

In: detection (signals + score + verdict), verdict cache per fingerprint, post-detection actions, events, middleware, config, artisan commands.
Out: analytics storage (`laravel-visit-analytics` consumes this package), Filament UI (a later `filament-bot-detector`, optional).

```
laravel-bot-detector/
  config/bot-detector.php
  database/migrations/        # optional bot_detections table
  src/
    BotDetectorServiceProvider.php
    Contracts/{Signal,ResponseAction}.php
    Data/{RequestFingerprint,Detection}.php
    Enums/{Verdict,BotKind}.php
    Signals/{UserAgentSignal,MissingHeadersSignal,KnownBotSignal,RateSignal,HoneypotSignal}.php
    Actions/{Flag,Log,Throttle,Block,Challenge,Tarpit}.php       # post-detection handlers
    Events/BotDetected.php
    Http/Middleware/DetectBots.php
    BotDetector.php
    Console/{VerifyBotsCommand}.php
  tests/
```

## 3. Detection

1. `Contracts\Signal`: `evaluate(RequestFingerprint $fp): int` (weight, 0 = no evidence). The reference's two detectors become
   `UserAgentSignal` (Matomo, plus the fallback regex) and `MissingHeadersSignal` (the weights keep their config keys).
2. `RequestFingerprint` (readonly DTO): user agent, `Accept*` headers, `Sec-Fetch-Site`, ip, path, method, route name. Built from the request in one place so signals stay pure and testable.
3. `BotDetector::inspect(Request): Detection` sums the signals and returns `Detection(score, verdict, kind, matched_signals)`.
   `Verdict`: `Human`, `GoodBot`, `Suspicious`, `Automated`. Thresholds from config (`suspicious_at`, `automated_at`; reference uses a single threshold of 4).
4. `matomo/device-detector` is a **suggested** dependency: `UserAgentSignal` is registered only when the class exists, so the package works with just the header signals.
5. **Good bots** (`KnownBotSignal`): Googlebot, Bingbot, etc. must not be blocked. A UA claim is forgeable, so offer verification by reverse DNS + forward confirm
   (`gethostbyaddr`, cached) with the verdict `GoodBot` only when verified; unverified claims count as `Suspicious`. `bot-detector:verify {ip} {ua}` helps debugging.
   Config `good_bots` maps UA pattern -> allowed hostname suffixes (`.googlebot.com`, `.search.msn.com`).
6. Keep the reference's **one-way escalation per session** as an option for stateful consumers (analytics): the package exposes `Detection::max(Detection $other)`; storing the session score stays in the consumer.
7. **NEW signals** (all optional, off by default): `RateSignal` (requests per minute per ip via `RateLimiter`), `HoneypotSignal` (hit on a path/field no human sees; `route('bot-trap')` + hidden form field), `PathProbeSignal` (`/wp-login.php`, `/.env`, `/phpmyadmin`).

## 4. NEW: post-detection handling

This is the new part. A `ResponseAction` runs when the verdict reaches a configured level.

```php
// config/bot-detector.php
'actions' => [
    'good_bot'   => [Log::class],
    'suspicious' => [Flag::class, Throttle::class],
    'automated'  => [Flag::class, Log::class, Block::class],
],
'except_paths' => ['up', 'robots.txt', 'sitemap*.xml', 'llms.txt'],
'except_ips'   => [],
```

- `Flag`: `request->attributes->set('bot_detection', $detection)` so downstream code (analytics, forms) reads it. Always safe.
- `Log`: dedicated log channel and/or `bot_detections` table (ip hash, ua, score, signals, route); retention via `model:prune`.
- `Throttle`: `RateLimiter` with a stricter bucket for that fingerprint; 429 with `Retry-After`.
- `Block`: 403 (configurable status, default 403, optional empty body); respects `except_paths` and verified good bots.
- `Challenge`: hook for a captcha/proof-of-work page; the package defines the contract, an implementation lives in the host (Turnstile etc.).
- `Tarpit`: delay then serve a minimal response; documented as dangerous with php-fpm workers, off by default.
- Per-route override: `Route::middleware('bot-detector:block')` / `bot-detector:log-only`. Per-action override with a `#[AllowBots]` attribute on controllers, or a `BotPolicy` closure registered in a provider.
- `Events\BotDetected` is dispatched for every non-human verdict, so hosts can add their own handling (Cloudflare firewall rule via API, Slack alert on spikes, ban list).
- `dry_run` config (default **true** at first install): runs every signal and logs/flags but never blocks. Essential to tune the threshold before enforcing.
- Ban list: `bot_bans` table (ip or cidr, reason, expires_at), checked first; `bot-detector:ban`, `bot-detector:unban`, auto-ban after N automated verdicts within a window.

## 5. Privacy

Header-based classification stores no personal data. The reference stores the classification **regardless of consent** (it filters non-human traffic, it does
not identify anyone) while IP and raw UA stay behind analytics consent. Keep that stance: the detector works on transient data; the logging action must store IP hashed
(or truncated) by default and document that raw IP logging needs a legal basis. Cross-reference `laravel-consent`.

## 6. Tests to write (port + new)

- Port: a modern browser header set scores 0; a UA claiming Chrome without `Sec-Fetch-Site` scores the configured weight; a UA not claiming Chrome is not penalised for missing Sec-Fetch; threshold boundary; one-way escalation helper.
- Port: Matomo fixtures: Googlebot, curl, python-requests, Go-http-client -> bot; WhatsApp/Opera Mini via fallback patterns.
- New: each action under each verdict; `dry_run` never blocks; `except_paths`; good bot verified vs spoofed (fake resolver); ban expiry; event dispatched once per request.
- Arch: `src/` has no dependency on analytics, Filament or Livewire.

## 7. Starter kit and the other packages

- `laravel-visit-analytics` depends on this package: its `TrackVisit` calls `BotDetector::inspect()` and stores `bot_score`/`device_type`; the reference's `DeviceTypeDetector` and `BotSignalDetector` are deleted from the app once adopted.
- Kit module `bot-detector` (optional): installs the package, appends the middleware to the `web` group, publishes config with `dry_run => true`.

## 8. Order of work

1. Signals + `Detection` + `BotDetector` with the ported tests (pure logic).
2. Middleware with `Flag` and `Log`; `dry_run`.
3. `Throttle`, `Block`, ban list, `BotDetected`.
4. Good-bot verification, README with a tuning guide ("run in dry_run for a week, inspect, then enforce").
5. Switch simonecerruti.com to it, then wire `laravel-visit-analytics`.

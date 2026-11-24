# Release Notes

## [Unreleased](https://github.com/SimoneCerruti/laravel-bot-detector/compare/v0.1.0...main)


## [v0.1.0](https://github.com/SimoneCerruti/laravel-bot-detector/releases/tag/v0.1.0) - 2026-11-24

Initial pre-release, to be run in dry run on live sites before v1.0.0.

### Detection

- `BotDetector::detect($request)` facade and `$request->botDetection()` macro return a `Detection`, computed at most once per request, with or without the middleware.
- `Detection` carries the `Verdict` (`Human`, `GoodBot`, `Suspicious`, `Automated`), the `Reason` that decided it, the header score with the matched signals, what the client claims to be, its bot category and the request fingerprint; `isBot()` and `isGoodBot()` shortcuts.
- Header signals with configurable weights and thresholds: missing `Accept-Language`, `Accept`, `Accept-Encoding`; missing `Sec-Fetch-Site` and `Sec-CH-UA` over HTTPS for browsers that send them; `HeadlessChrome` in the User-Agent or `Sec-CH-UA`. Custom signals through the `Signal` contract.
- Self-declared bots and HTTP libraries classified via `matomo/device-detector`, with Matomo's regexes cached in a configurable store.
- Good bots (Google, Bing, Apple, DuckDuckGo, Yandex) verified by reverse and forward DNS, cached per IP, with a configurable DNS timeout; a DNS error never blocks a real crawler.
- Optional vulnerability-probe path classification.

### Middleware and reactions

- `bot-detector` middleware running configurable reactions per verdict: `Log`, `Throttle` (429 with `Retry-After`), `Block` (403 by default); custom reactions through the `Reaction` and `RespondsToDeny` contracts.
- Dry run on by default: denials are logged but never enforced.
- `bot-detector:log-only` mode, `except_paths` and `except_ips`, and a `BotPolicy` closure to override reactions per request.
- Logged IPs are an HMAC keyed by the app key unless raw IPs are enabled.
- `BotDetected` event for every non-human verdict, once per request.

### Tooling

- `bot-detector:verify {ip} {ua} {--header=*}` replays a visitor with real DNS and no side effects.
- Environment-specific settings read from `BOT_DETECTOR_*` variables.
- README with a tuning guide and a Laravel Boost skill.

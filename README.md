<div align="center">
    <h1>Laravel Bot Detector</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/simonecerruti/laravel-bot-detector"><img src="https://img.shields.io/packagist/v/simonecerruti/laravel-bot-detector.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/simonecerruti/laravel-bot-detector"><img src="https://img.shields.io/packagist/php-v/simonecerruti/laravel-bot-detector.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/simonecerruti/laravel-bot-detector"><img src="https://badge.laravel.cloud/badge/simonecerruti/laravel-bot-detector?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/simonecerruti/laravel-bot-detector/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/simonecerruti/laravel-bot-detector/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/simonecerruti/laravel-bot-detector"><img src="https://img.shields.io/packagist/dt/simonecerruti/laravel-bot-detector.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Detect bots in your Laravel app, and decide what to do about them.

Laravel Bot Detector looks at every request it protects and gives it one of four **verdicts**: a real visitor, a verified search engine crawler, a suspicious client, or an automated one. For each verdict you choose **reactions**: write a log line, throttle, block, or anything you build yourself, such as a captcha challenge. It ships in **dry run**: it watches and logs, but blocks nothing until you switch enforcement on.

- [Installation](#installation)
- [Behind a proxy or load balancer: configure TrustProxies](#behind-a-proxy-or-load-balancer-configure-trustproxies)
- [Quick start](#quick-start)
- [How a request is classified](#how-a-request-is-classified)
- [Reading the detection in your code](#reading-the-detection-in-your-code)
- [Reactions](#reactions)
- [Writing your own reaction: a Turnstile challenge](#writing-your-own-reaction-a-turnstile-challenge)
- [Choosing reactions per request: the bot policy](#choosing-reactions-per-request-the-bot-policy)
- [Events](#events)
- [Adding your own signals](#adding-your-own-signals)
- [Debugging a visitor](#debugging-a-visitor)
- [Tuning guide: from dry run to enforcement](#tuning-guide-from-dry-run-to-enforcement)
- [Privacy](#privacy)
- [Configuration reference](#configuration-reference)

## Installation

Install the package via Composer:

```bash
composer require simonecerruti/laravel-bot-detector
```

Publish the configuration file to `config/bot-detector.php`:

```bash
php artisan vendor:publish --tag="bot-detector-config"
```

The `bot-detector` tag publishes every package resource at once; today that is the same configuration file.

```bash
php artisan vendor:publish --tag="bot-detector"
```

Every option has a working default, so publishing is only needed when you want to change one.

## Behind a proxy or load balancer: configure TrustProxies

> [!WARNING]
> If your app runs behind a load balancer, a CDN (Cloudflare, CloudFront) or a reverse proxy, configure Laravel's trusted proxies **before** you turn enforcement on. Without it, the package sees the proxy, not the visitor.

When the proxy is not trusted, every request looks like it comes from the proxy's IP address over plain HTTP. The consequences:

- **Real search engine crawlers get blocked.** Googlebot is verified by checking that its IP belongs to Google. The proxy's IP never does, so the real Googlebot is classified `automated` and blocked as an impostor.
- **Visitors share one throttle bucket.** Throttling counts requests per IP and User-Agent, so every suspicious visitor using the same browser hits the same limit.
- **IP exceptions stop working.** `except_ips` is compared with the proxy's IP, not the visitor's.
- **Browser checks switch off.** The checks on `Sec-Fetch-Site` and `Sec-CH-UA` only run over HTTPS, so automated clients pretending to be a browser slip through.

Trust your proxies in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: [
        '10.0.0.0/8', // your load balancer's addresses
    ]);
})
```

Use `at: '*'` only when the app cannot be reached except through the proxy. See [Configuring Trusted Proxies](https://laravel.com/docs/requests#configuring-trusted-proxies) in the Laravel documentation for the proxy headers and the Cloudflare ranges.

## Quick start

Add the `bot-detector` middleware to the routes you want to protect:

```php
use Illuminate\Support\Facades\Route;

Route::middleware('bot-detector')->group(function () {
    Route::get('/', HomeController::class);
    Route::post('/contact', ContactController::class);
});
```

With the default configuration this is what happens on those routes:

| Verdict | Reactions | Effect in dry run (default) | Effect when enforcing |
| --- | --- | --- | --- |
| `human` | none | passes | passes |
| `good_bot` | none | passes | passes |
| `suspicious` | log, throttle | logged, passes | logged, `429` after 30 requests a minute |
| `automated` | log, block | logged, passes | logged, empty `403` |

Routes without the middleware are never touched. When the logs look right (see the [tuning guide](#tuning-guide-from-dry-run-to-enforcement)), turn enforcement on in `config/bot-detector.php`:

```php
'dry_run' => [
    'enabled' => false,
    // ...
],
```

## How a request is classified

Each request gets exactly one verdict. The rules below are checked in this order, and the first one that applies wins.

1. **Claims to be a known good bot.** The User-Agent matches one of the `good_bots` patterns (Googlebot, Bingbot, Applebot, DuckDuckBot and YandexBot by default). The package checks the claim by DNS: the IP must point back to a hostname of that bot (such as `crawl-66-249-66-1.googlebot.com`) and that hostname must point to the same IP.
   - Confirmed: `good_bot`.
   - Not confirmed: `automated`, because anybody can write "Googlebot" in a User-Agent.
   - DNS failed: `suspicious`, so a DNS outage never blocks the real crawler. The check is retried on the next request.

   Confirmed answers are cached per IP for 24 hours (`good_bots.cache_ttl`).
2. **Declares itself a bot or an HTTP library.** SEO crawlers, AI crawlers, uptime monitors, social link previews, `curl`, `python-requests` and similar tools are recognised from the User-Agent: `automated`.
3. **Requests a probe path.** When `probe_paths.enabled` is on, a request to a path that vulnerability scanners try (such as `.env`, `wp-login.php`, `.git/config`) is `automated` on the first hit. Off by default, since your app may serve one of those paths.
4. **Scores too high.** Every other request is scored by **signals**: missing details that real browsers always send. Each signal that matches adds its weight to the score.

   | Signal | Matches when | Weight |
   | --- | --- | --- |
   | `HeadlessChrome` | the User-Agent or `Sec-CH-UA` announces headless Chrome | 5 |
   | `MissingSecFetchSite` | over HTTPS, a client claiming Chrome 76+, Firefox 90+ or Safari 16.4+ sends no `Sec-Fetch-Site` | 3 |
   | `MissingSecChUa` | over HTTPS, a client claiming Chromium 89+ sends no `Sec-CH-UA` | 3 |
   | `MissingAcceptLanguage` | no `Accept-Language` | 3 |
   | `MissingAccept` | no `Accept` | 2 |
   | `MissingAcceptEncoding` | no `Accept-Encoding` | 1 |

   A score of `suspicious_at` (3) or more is `suspicious`, a score of `automated_at` (5) or more is `automated`.
5. Otherwise the request is `human`.

The score and the matched signals are computed for every request, whatever rule decided the verdict, so you can always see them in the logs.

## Reading the detection in your code

The detection is available anywhere you have the request, with or without the middleware: in controllers, form requests, views, jobs you dispatch. Use the facade or the request macro, whichever you prefer:

```php
use SimoneCerruti\BotDetector\Facades\BotDetector;

$detection = BotDetector::detect($request);
$detection = $request->botDetection();
```

The detection is computed once per request and reused, so calling it several times, or after the middleware, costs nothing. Skipping it in analytics, for example:

```php
if (! $request->botDetection()->isBot()) {
    PageView::record($request);
}
```

A detection carries:

| Property / method | Content |
| --- | --- |
| `verdict` | a `SimoneCerruti\BotDetector\Enums\Verdict`: `Human`, `GoodBot`, `Suspicious` or `Automated` |
| `score` | the sum of the weights of the matched signals |
| `signals` | the matched signals, as signal class => weight |
| `claimed_as` | what a self-declared bot or HTTP library says it is, such as `Googlebot` or `curl`; `null` otherwise |
| `category` | the bot category, such as `Search bot` or `Crawler`, when known; `null` otherwise |
| `verified` | `true` or `false` when a good-bot claim was checked by DNS; `null` when there was no claim or DNS failed |
| `fingerprint` | what the detection looked at: `user_agent`, `ip`, `secure`, `accept`, `accept_language`, `accept_encoding`, `sec_fetch_site`, `sec_ch_ua`, `path`, `method`, `route` |
| `isBot()` | `true` for every verdict except `human` |
| `isGoodBot()` | `true` for a verified good bot |
| `toArray()` | the whole detection as an array |

## Reactions

The middleware runs the reactions you configure for the request's verdict:

```php
use SimoneCerruti\BotDetector\Reactions\Block;
use SimoneCerruti\BotDetector\Reactions\Log;
use SimoneCerruti\BotDetector\Reactions\Throttle;

'reactions' => [
    'good_bot' => [],
    'suspicious' => [Log::class, Throttle::class],
    'automated' => [Log::class, Block::class],
],
```

How reactions run:

- Every reaction of the verdict runs, in the order listed, even after one has denied the request. So `Log` placed after `Block` still logs.
- The request is **denied** when at least one reaction denies it. It then gets the response of the first denying reaction.
- Human requests run no reactions.
- To disable a reaction, remove it from the list.

The built-in reactions:

| Reaction | What it does |
| --- | --- |
| `Log` | Writes a `Bot detected` entry to your log, then lets the request through. Configure the channel and level under `log`. |
| `Throttle` | Allows each IP + User-Agent `throttle.max_requests` requests every `throttle.per_seconds` seconds (30 a minute by default), counted in your default cache store. Beyond that it denies with an empty `429` and a `Retry-After` header. |
| `Block` | Denies with an empty response of status `deny_status` (`403` by default). |

### Never enforce on some routes

Requests to `except_paths` or from `except_ips` skip detection entirely: no reactions, no events. Paths have no leading slash and `*` matches anything; IPs can be single addresses or ranges such as `10.0.0.0/8`.

```php
'except_paths' => ['up', 'robots.txt', 'sitemap*.xml', 'llms.txt', 'webhooks/*'],
'except_ips' => ['203.0.113.10'],
```

To keep watching a route without ever blocking it, use the `log-only` mode. Reactions and events run as usual, but a denial is never enforced:

```php
Route::get('/pricing', PricingController::class)->middleware('bot-detector:log-only');
```

An unknown mode, such as a typo in `bot-detector:log-onyl`, throws an error instead of silently enforcing.

### Dry run

While `dry_run.enabled` is on (the default), every reaction still runs, but a denied request is let through. Logs, throttle counters and events behave exactly as they will in production, so you can see what enforcement would do before it does it.

## Writing your own reaction: a Turnstile challenge

A reaction is a class implementing `SimoneCerruti\BotDetector\Contracts\Reaction`. Its `decide()` method returns `Decision::Allow` or `Decision::Deny`. To send your own response when it denies, also implement `SimoneCerruti\BotDetector\Contracts\RespondsToDeny`; without it, a denied request gets the `deny_status` response. Reactions are resolved from the container, so you can inject dependencies in the constructor.

This example sends suspicious visitors to a [Cloudflare Turnstile](https://developers.cloudflare.com/turnstile/) challenge, and lets them through once they solved it.

```php
namespace App\BotReactions;

use Illuminate\Http\Request;
use SimoneCerruti\BotDetector\Contracts\Reaction;
use SimoneCerruti\BotDetector\Contracts\RespondsToDeny;
use SimoneCerruti\BotDetector\Data\Detection;
use SimoneCerruti\BotDetector\Enums\Decision;
use Symfony\Component\HttpFoundation\Response;

final class TurnstileChallenge implements Reaction, RespondsToDeny {
    public function decide(Request $request, Detection $detection): Decision {
        return $request->session()->get('turnstile_passed') ? Decision::Allow : Decision::Deny;
    }

    public function denyResponse(Request $request, Detection $detection): Response {
        $request->session()->put('url.intended', $request->fullUrl());

        return redirect()->route('challenge');
    }
}
```

Use it for the `suspicious` verdict, in place of `Throttle`:

```php
'suspicious' => [Log::class, App\BotReactions\TurnstileChallenge::class],
```

The challenge page itself is ordinary app code. Keep it **outside** the `bot-detector` middleware, or visitors would be redirected to it forever:

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

Route::view('/challenge', 'challenge')->name('challenge'); // renders the Turnstile widget in a form posting to /challenge

Route::post('/challenge', function (Request $request) {
    $passed = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
        'secret' => config('services.turnstile.secret'),
        'response' => $request->input('cf-turnstile-response'),
        'remoteip' => $request->ip(),
    ])->json('success');

    abort_unless($passed, 403);
    $request->session()->put('turnstile_passed', true);

    return redirect()->intended('/');
});
```

Two things to keep in mind:

- The reaction reads the session, so protect routes in the `web` middleware group, where the session is started.
- In dry run the denial is let through, so the challenge only appears once enforcement is on.

## Choosing reactions per request: the bot policy

The configuration picks reactions by verdict only. When the choice depends on the request (a route, a signed-in user, a bot you want to welcome), register a **policy** in a service provider. It receives the request, the detection and the configured reactions, and returns the reactions to run:

```php
use Illuminate\Http\Request;
use SimoneCerruti\BotDetector\Data\Detection;
use SimoneCerruti\BotDetector\Facades\BotDetector;
use SimoneCerruti\BotDetector\Reactions\Log;

public function boot(): void {
    BotDetector::usePolicy(function (Request $request, Detection $detection, array $reactions): array {
        // Feed readers are automated by nature: log them, never block them.
        if ($request->routeIs('feed') && $detection->isBot()) {
            return [Log::class];
        }

        // Signed-in users already proved who they are.
        if ($request->user() !== null) {
            return [];
        }

        return $reactions;
    });
}
```

The policy runs for every request the middleware inspects, human ones included (with an empty `$reactions`). Return `$reactions` unchanged to keep the configured behaviour. Dry run and `log-only` still apply to whatever the policy returns. Register a single policy: a second `usePolicy()` call replaces the first.

## Events

For every non-human verdict, the middleware dispatches `SimoneCerruti\BotDetector\Events\BotDetected` once per request. Use it to add your own handling, such as an alert on a spike or a firewall rule at your CDN.

```php
use Illuminate\Support\Facades\Event;
use SimoneCerruti\BotDetector\Events\BotDetected;

Event::listen(function (BotDetected $event): void {
    if ($event->dry_run) {
        return; // still tuning: skip anything irreversible
    }

    BannedIp::firstOrCreate(['ip' => $event->detection->fingerprint->ip]);
});
```

The event carries `detection`, `request` and `dry_run` (whether dry run is on). Human requests and requests skipped by `except_paths` / `except_ips` dispatch nothing.

To keep your listeners silent until enforcement is on, turn off `dry_run.dispatch_events`. Outside dry run, events are always dispatched.

## Adding your own signals

A signal is a class implementing `SimoneCerruti\BotDetector\Contracts\Signal`. Its `matches()` method receives the request's fingerprint (see [Reading the detection](#reading-the-detection-in-your-code)) and returns `true` when it finds evidence of automation.

```php
namespace App\BotSignals;

use SimoneCerruti\BotDetector\Contracts\Signal;
use SimoneCerruti\BotDetector\Data\RequestFingerprint;

final class OldChrome implements Signal {
    public function matches(RequestFingerprint $fingerprint): bool {
        return preg_match('~Chrome/([1-9]\d)\.~', (string) $fingerprint->user_agent) === 1;
    }
}
```

Register it in `signals` with the weight it adds to the score:

```php
'signals' => [
    // ...the built-in signals
    App\BotSignals\OldChrome::class => 2,
],
```

The same list tunes the built-in signals: change a weight, or remove a signal to disable it.

## Debugging a visitor

To understand why a visitor got its verdict, replay it from the terminal with the IP and User-Agent from your logs:

```bash
php artisan bot-detector:verify 66.249.66.1 "Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)"
```

The command runs the full detection over HTTPS, real DNS included, and prints the verdict, score, what the client claims to be, its category, whether DNS verified it, and the matched signals with their weights. It runs no reactions, writes no log and dispatches no event.

The replayed request carries only the User-Agent, so the missing-header signals match. Add the headers you know with `--header`, repeated as needed:

```bash
php artisan bot-detector:verify 203.0.113.7 "Mozilla/5.0 ... Chrome/131.0.0.0 Safari/537.36" \
    --header="Accept-Language: en-US,en;q=0.9" \
    --header="Accept: text/html"
```

## Tuning guide: from dry run to enforcement

1. **Configure trusted proxies.** If anything sits in front of your app, follow [the TrustProxies section](#behind-a-proxy-or-load-balancer-configure-trustproxies) first.
2. **Protect your routes and deploy in dry run.** Keep `dry_run.enabled` on and leave the default reactions: `Log` records every non-human verdict.
3. **Collect about a week of traffic.** A week covers weekday and weekend visitors, scheduled crawlers and the monitors you forgot about.
4. **Read the logs.** Each `Bot detected` entry has `ip` (hashed, see [Privacy](#privacy)), `user_agent`, `score`, `signals`, `verdict`, `method`, `path` and `route`. Look for:
   - visitors you know are real but got `suspicious` or `automated`: check which signals matched;
   - services you rely on: uptime monitors, payment webhooks, feed readers, link previews of the social networks you share on;
   - paths that only machines should call, such as webhooks or health checks.
5. **Adjust.**
   - A signal wrong for your audience: lower its weight or remove it from `signals`.
   - Too strict or too lax overall: move `suspicious_at` and `automated_at`.
   - Machine-only endpoints: add them to `except_paths`, or the callers' addresses to `except_ips`.
   - Bots you want, decided per request: write a [bot policy](#choosing-reactions-per-request-the-bot-policy).
   - A single puzzling visitor: replay it with [`bot-detector:verify`](#debugging-a-visitor).
6. **Enforce.** Set `dry_run.enabled` to `false`. Keep `Log` in the reactions for a while and watch for `403` and `429` responses you did not expect.

## Privacy

IP addresses are personal data in many jurisdictions. What the package stores:

- **Logs.** The `Log` reaction writes the IP as an HMAC-SHA256 keyed by your `APP_KEY`: the same visitor gets the same value, so you can follow it across entries, but the address cannot be read back. Set `log.raw_ip` to `true` to log the address itself. Rotating `APP_KEY` changes every value.
- **Cache.** The good-bot DNS check caches the confirmed hostnames under a key containing the IP, for `good_bots.cache_ttl` seconds. The throttle counters use a hash of IP + User-Agent.
- **Your own code.** The detection you read in your code, pass to events or serialise with `toArray()` holds the raw IP in `fingerprint`. Hash or drop it before storing it.

## Configuration reference

All options live in `config/bot-detector.php`; the published file documents each one.

| Key | Default | Effect |
| --- | --- | --- |
| `except_paths` | `up`, `robots.txt`, `sitemap*.xml`, `llms.txt` | paths the middleware skips entirely |
| `except_ips` | `[]` | IPs or ranges the middleware skips entirely |
| `signals` | the six built-in signals | signal class => weight added to the score |
| `suspicious_at` | `3` | score from which a request is `suspicious` |
| `automated_at` | `5` | score from which a request is `automated` |
| `probe_paths.enabled` | `false` | classify requests to probe paths as `automated` |
| `probe_paths.paths` | `.env`, `wp-login.php`, `phpmyadmin`, ... | the probe paths, `*` as wildcard |
| `good_bots.bots` | Googlebot, Bingbot, Applebot, DuckDuckBot, YandexBot | User-Agent regex => allowed hostname suffixes |
| `good_bots.cache_ttl` | `86400` | seconds a DNS confirmation stays cached |
| `device_detector.cache_store` | `file` | cache store holding the User-Agent rules that recognise bots (`null` for the app default) |
| `reactions` | see [Reactions](#reactions) | verdict => reactions to run |
| `log.channel` | `null` (app default) | log channel of the `Log` reaction |
| `log.level` | `info` | log level of the `Log` reaction |
| `log.raw_ip` | `false` | log the IP instead of its HMAC |
| `throttle.max_requests` | `30` | requests allowed per window by `Throttle` |
| `throttle.per_seconds` | `60` | length of the `Throttle` window |
| `dry_run.enabled` | `true` | run reactions but never deny |
| `dry_run.dispatch_events` | `true` | dispatch `BotDetected` during dry run |
| `deny_status` | `403` | status of the empty response a denied request gets |

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Laravel Bot Detector! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Simone Cerruti](https://github.com/simonecerruti)
- [All Contributors](../../contributors)

## License

Laravel Bot Detector is open-sourced software licensed under the [MIT license](LICENSE.md).

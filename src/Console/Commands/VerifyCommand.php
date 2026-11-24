<?php

declare(strict_types=1);

namespace SimoneCerruti\BotDetector\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SimoneCerruti\BotDetector\BotDetector;

/** Replays a visitor through the full detection, real DNS included, and prints the Detection: no reactions, no log, no events. */
final class VerifyCommand extends Command {
    protected $signature = 'bot-detector:verify
        {ip : The visitor IP}
        {ua : The visitor User-Agent}
        {--header=* : A request header as "Name: value", repeatable}';

    protected $description = 'Replay a visitor and print its bot detection';

    public function handle(BotDetector $detector): int {
        // HTTPS, as in production: the Sec-* signals only apply over HTTPS.
        $request = Request::create('https://localhost/', server: ['REMOTE_ADDR' => $this->argument('ip')]);

        // Request::create() adds browser-like defaults that would hide the missing-header signals.
        $request->headers->remove('Accept');
        $request->headers->remove('Accept-Language');
        $request->headers->remove('Accept-Charset');
        /** @var string $user_agent */
        $user_agent = $this->argument('ua');
        $request->headers->set('User-Agent', $user_agent);

        /** @var list<string> $headers */
        $headers = $this->option('header');

        foreach ($headers as $header) {
            if (! Str::contains($header, ':')) {
                $this->components->error("Invalid header [{$header}]: use \"Name: value\".");

                return self::FAILURE;
            }

            $request->headers->set(trim(Str::before($header, ':')), trim(Str::after($header, ':')));
        }

        $detection = $detector->detect($request);

        $this->components->twoColumnDetail('Verdict', $detection->verdict->value);
        $this->components->twoColumnDetail('Score', (string) $detection->score);
        $this->components->twoColumnDetail('Claimed as', $detection->claimed_as ?? '-');
        $this->components->twoColumnDetail('Category', $detection->category ?? '-');
        $this->components->twoColumnDetail('Verified', match ($detection->verified) {
            true => 'yes',
            false => 'no',
            null => '-',
        });
        $this->components->twoColumnDetail('Signals', $detection->signals === [] ? 'none' : '');

        foreach ($detection->signals as $signal => $weight) {
            $this->components->twoColumnDetail("  {$signal}", (string) $weight);
        }

        return self::SUCCESS;
    }
}

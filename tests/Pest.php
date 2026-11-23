<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use SimoneCerruti\BotDetector\Tests\TestCase;

pest()->extend(TestCase::class)->in(__DIR__);

/**
 * Build a request carrying the headers a real browser sends, overridden or removed (null) as given.
 *
 * @param  array<string, string|null>  $headers
 */
function browserRequest(array $headers = []): Request
{
    $request = Request::create('/');

    foreach (['Accept-Language' => 'it-IT,it;q=0.9,en;q=0.8', ...$headers] as $name => $value) {
        // Request::create() adds defaults for some headers, so a missing header must be removed explicitly.
        $value === null ? $request->headers->remove($name) : $request->headers->set($name, $value);
    }

    return $request;
}

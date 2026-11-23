<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use SimoneCerruti\BotDetector\Tests\TestCase;

pest()->extend(TestCase::class)->in(__DIR__);

/**
 * Build a request carrying the headers a modern Chrome sends, overridden or removed (null) as given.
 *
 * @param  array<string, string|null>  $headers
 */
function browserRequest(array $headers = [], bool $secure = true, string $path = '/', string $ip = '127.0.0.1'): Request {
    $request = Request::create(($secure ? 'https://localhost' : 'http://localhost').$path, server: ['REMOTE_ADDR' => $ip]);

    foreach ([
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'it-IT,it;q=0.9,en;q=0.8',
        'Accept-Encoding' => 'gzip, deflate, br, zstd',
        'Sec-Fetch-Site' => 'none',
        'Sec-CH-UA' => '"Google Chrome";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
        ...$headers,
    ] as $name => $value) {
        // Request::create() adds defaults for some headers, so a missing header must be removed explicitly.
        $value === null ? $request->headers->remove($name) : $request->headers->set($name, $value);
    }

    return $request;
}

<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use SimoneCerruti\BotDetector\BotDetector;
use SimoneCerruti\BotDetector\BotDetectorServiceProvider;
use SimoneCerruti\BotDetector\Contracts\Signal;
use SimoneCerruti\BotDetector\Data\Detection;
use SimoneCerruti\BotDetector\Data\RequestFingerprint;
use SimoneCerruti\BotDetector\Enums\Verdict;
use SimoneCerruti\BotDetector\Facades\BotDetector as BotDetectorFacade;
use SimoneCerruti\BotDetector\Signals\MissingAccept;
use SimoneCerruti\BotDetector\Signals\MissingAcceptEncoding;
use SimoneCerruti\BotDetector\Signals\MissingAcceptLanguage;
use SimoneCerruti\BotDetector\Signals\MissingSecChUa;
use SimoneCerruti\BotDetector\Signals\MissingSecFetchSite;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/** The classes of the public API, covered by semver: every other class of the package is internal. */
const PUBLIC_API = [
    BotDetectorServiceProvider::class,
    BotDetector::class,
    BotDetectorFacade::class,
    Signal::class,
    Detection::class,
    RequestFingerprint::class,
    Verdict::class,
    MissingAcceptLanguage::class,
    MissingAccept::class,
    MissingAcceptEncoding::class,
    MissingSecFetchSite::class,
    MissingSecChUa::class,
];

arch()->preset()->php();

arch()->preset()->security();

arch('it will not use dd(), ddd(), env(), or exit()')
    ->expect(['dd', 'ddd', 'env', 'exit'])
    ->each->not->toBeUsed();

arch('the package source declares strict types')
    ->expect('SimoneCerruti\BotDetector')
    ->toUseStrictTypes();

arch('the concrete classes are final')
    ->expect('SimoneCerruti\BotDetector')
    ->classes()
    ->toBeFinal();

arch('the signals implement the signal contract')
    ->expect('SimoneCerruti\BotDetector\Signals')
    ->classes()
    ->toImplement(Signal::class);

it('marks as internal every class outside the public API', function (string $class): void {
    $doc_comment = (string) new ReflectionClass($class)->getDocComment();

    expect(Str::contains($doc_comment, '@internal'))->toBe(! in_array($class, PUBLIC_API, true));
})->with(fn (): array => collect(Finder::create()->files()->in(__DIR__.'/../src'))
    ->values()
    ->map(fn (SplFileInfo $file): string => 'SimoneCerruti\\BotDetector\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname()))
    ->all());

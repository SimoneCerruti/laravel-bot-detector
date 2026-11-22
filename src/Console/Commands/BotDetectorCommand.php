<?php

declare(strict_types=1);

namespace BotDetector\BotDetector\Console\Commands;

use Illuminate\Console\Command;

class BotDetectorCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'laravel-bot-detector:placeholder';

    /**
     * The command description.
     */
    protected $description = 'Placeholder Artisan command shipped by the package laravel-bot-detector.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('BotDetector placeholder command executed.');

        return self::SUCCESS;
    }
}

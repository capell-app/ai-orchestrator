<?php

declare(strict_types=1);

namespace Capell\Assistant\Console\Commands;

use Capell\Assistant\Support\OpenAIProvider;
use Illuminate\Console\Command;

class TestOpenAiConnectionCommand extends Command
{
    protected $signature = 'capell:admin-test-openai';

    protected $description = 'Test connectivity to OpenAI';

    public function handle(OpenAIProvider $provider): int
    {
        $healthy = $provider->isAvailable();
        $this->info($healthy ? 'OpenAI API reachable.' : 'OpenAI API unreachable.');

        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}

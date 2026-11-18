<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Data;

use Capell\AIOrchestrator\Support\Ai\BrandVoice;
use Spatie\LaravelData\Data;

final class AIOrchestratorRuntimeSettingsData extends Data
{
    /**
     * @param  array<string, mixed>  $provider
     * @param  array<string, mixed>  $prompts
     * @param  array<string, mixed>  $rateLimiting
     */
    public function __construct(public readonly array $provider, public readonly array $prompts, public readonly array $rateLimiting, public readonly BrandVoice $brandVoice = new BrandVoice) {}
}

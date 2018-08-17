<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Settings;

use Capell\Core\Contracts\SettingsContract;
use Override;
use Spatie\LaravelSettings\Settings;

class AIOrchestratorSettings extends Settings implements SettingsContract
{
    public bool $page_content_generator;

    public bool $page_title_suggestions;

    public bool $ai_creator;

    public string $ai_provider;

    public string $ai_model;

    public string $ai_api_key;

    public string $image_provider;

    public string $image_model;

    public string $image_default_size;

    /** @phpstan-var array<string, bool|int|string|array<string, string|int|float>> */
    public array $prompts;

    public static function group(): string
    {
        return 'ai-orchestrator';
    }

    public static function repository(): ?string
    {
        return 'ai-orchestrator';
    }

    /** @return list<string> */
    #[Override]
    public static function encrypted(): array
    {
        return ['ai_api_key'];
    }
}

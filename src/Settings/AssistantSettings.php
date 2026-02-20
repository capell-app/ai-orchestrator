<?php

declare(strict_types=1);

namespace Capell\Assistant\Settings;

use Spatie\LaravelSettings\Settings;

class AssistantSettings extends Settings
{
    public bool $page_content_generator;

    public bool $page_title_suggestions;

    public static function group(): string
    {
        return 'assistant';
    }
}
